<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\PurchaseOrder;
use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Daily reminders for approaching and missed deadlines. Each record is
 * reminded at most once per day (tracked in its reminded_at column), so
 * running the command more than once is harmless.
 */
#[Signature('ndc:send-reminders')]
#[Description('Send deadline, overdue, meeting, expiry and warranty reminders')]
class SendRemindersCommand extends Command
{
    private int $sent = 0;

    public function handle(): int
    {
        $days = (int) setting('reminder_days_before_due', 3);
        $horizon = today()->addDays($days);

        $this->tasks($horizon);
        $this->correspondence($horizon);
        $this->meetings();
        $this->actionItems($horizon);
        $this->documents();
        $this->contracts();
        $this->warranties();
        $this->maintenance($horizon);

        $this->info("Sent {$this->sent} reminder(s).");

        return self::SUCCESS;
    }

    private function notRemindedToday(Builder $query, string $column = 'reminded_at'): Builder
    {
        return $query->where(fn ($q) => $q->whereNull($column)->orWhereDate($column, '<', today()));
    }

    private function tasks($horizon): void
    {
        $this->notRemindedToday(Task::query()->open()->whereNotNull('assigned_to')->whereNotNull('due_date')->whereDate('due_date', '<=', $horizon))
            ->with('assignee')->each(function (Task $task) {
                $overdue = $task->isOverdue();
                $this->send($task->assignee, $overdue ? 'task_overdue' : 'task_due',
                    $overdue ? 'Task overdue' : 'Task deadline approaching',
                    $overdue ? 'The task ":title" was due on :date.' : 'The task ":title" is due on :date.',
                    ['title' => $task->title, 'date' => $task->due_date->toDateString()],
                    route('tasks.show', $task));
                $task->forceFill(['reminded_at' => now()])->saveQuietly();
            });
    }

    private function correspondence($horizon): void
    {
        $this->notRemindedToday(Correspondence::query()->open()->whereNotNull('response_due_date')->whereDate('response_due_date', '<=', $horizon))
            ->with(['assignee', 'responsibleOfficer'])->each(function (Correspondence $letter) {
                $overdue = $letter->isOverdue();
                foreach (collect([$letter->assignee, $letter->responsibleOfficer])->filter()->unique('id') as $user) {
                    $this->send($user, $overdue ? 'correspondence_overdue' : 'correspondence_due',
                        $overdue ? 'Response overdue' : 'Response deadline approaching',
                        'A response to :ref ":subject" is due on :date.',
                        ['ref' => $letter->reference_number, 'subject' => $letter->subject, 'date' => $letter->response_due_date->toDateString()],
                        route('correspondence.show', $letter));
                }
                $letter->forceFill(['reminded_at' => now()])->saveQuietly();
            });
    }

    private function meetings(): void
    {
        Meeting::query()->where('status', 'scheduled')->whereNull('reminded_at')
            ->whereBetween('start_at', [now(), now()->addDay()])
            ->with('participants.user')->each(function (Meeting $meeting) {
                $users = $meeting->participants->pluck('user')->filter();
                if ($meeting->organizer_id) {
                    $users->push(User::query()->find($meeting->organizer_id));
                }
                foreach ($users->filter()->unique('id') as $user) {
                    $this->send($user, 'meeting_reminder', 'Meeting reminder',
                        '":title" starts :time at :location.',
                        ['title' => $meeting->title, 'time' => $meeting->start_at->format('Y-m-d H:i'), 'location' => (string) ($meeting->location ?: '—')],
                        route('meetings.show', $meeting));
                }
                $meeting->forceFill(['reminded_at' => now()])->saveQuietly();
            });
    }

    private function actionItems($horizon): void
    {
        $this->notRemindedToday(MeetingActionItem::query()->outstanding()->whereNotNull('assigned_to')->whereNotNull('due_date')->whereDate('due_date', '<=', $horizon))
            ->with(['assignee', 'meeting'])->each(function (MeetingActionItem $item) {
                $overdue = $item->isOverdue();
                $this->send($item->assignee, $overdue ? 'action_overdue' : 'action_due',
                    $overdue ? 'Meeting action overdue' : 'Meeting action due soon',
                    'Action from ":meeting" is due on :date: :action',
                    ['meeting' => (string) $item->meeting?->title, 'date' => $item->due_date->toDateString(), 'action' => mb_substr($item->description, 0, 120)],
                    route('meetings.show', $item->meeting_id));
                $item->forceFill(['reminded_at' => now()])->saveQuietly();
            });
    }

    private function documents(): void
    {
        $horizon = today()->addDays((int) setting('document_expiry_reminder_days', 30));

        $this->notRemindedToday(Document::query()->active()->whereNotIn('status', ['archived'])
            ->where(fn ($q) => $q->whereDate('expiry_date', '<=', $horizon)->orWhereDate('review_date', '<=', $horizon)))
            ->with('owner')->each(function (Document $document) {
                // Weekly at most for documents: they stay in the window for weeks.
                if ($document->reminded_at && $document->reminded_at->gt(now()->subDays(7))) {
                    return;
                }
                $expiry = $document->expiry_date && $document->expiry_date->lte(today()->addDays((int) setting('document_expiry_reminder_days', 30)));
                $this->send($document->owner, $expiry ? 'document_expiry' : 'document_review',
                    $expiry ? 'Document expiry approaching' : 'Document review due',
                    $expiry ? ':ref ":title" expires on :date.' : ':ref ":title" is due for review on :date.',
                    ['ref' => $document->reference_number, 'title' => $document->title, 'date' => ($expiry ? $document->expiry_date : $document->review_date)->toDateString()],
                    route('documents.show', $document));
                $document->forceFill(['reminded_at' => now()])->saveQuietly();
            });
    }

    private function contracts(): void
    {
        $horizon = today()->addDays((int) setting('document_expiry_reminder_days', 30));

        PurchaseOrder::query()->whereNotNull('contract_end_date')->whereBetween('contract_end_date', [today(), $horizon])
            ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<', now()->subDays(7)))
            ->each(function (PurchaseOrder $order) {
                $recipients = $this->usersWith('procurement.update')->push(User::query()->find($order->created_by));
                foreach ($recipients->filter()->unique('id') as $user) {
                    $this->send($user, 'contract_renewal', 'Contract renewal approaching',
                        'Contract :contract (order :po) ends on :date.',
                        ['contract' => (string) ($order->contract_reference ?: $order->po_number), 'po' => $order->po_number, 'date' => $order->contract_end_date->toDateString()],
                        route('purchase-orders.show', $order));
                }
                $order->forceFill(['reminded_at' => now()])->saveQuietly();
            });
    }

    private function warranties(): void
    {
        $horizon = today()->addDays((int) setting('warranty_reminder_days', 30));
        $recipients = null;

        Asset::query()->whereNotIn('status', ['retired', 'disposed'])->whereNull('warranty_reminded_at')
            ->whereBetween('warranty_expiry', [today(), $horizon])
            ->each(function (Asset $asset) use (&$recipients) {
                $recipients ??= $this->usersWith('assets.update');
                foreach ($recipients as $user) {
                    $this->send($user, 'warranty_expiry', 'Warranty expiring',
                        'The warranty for :tag :name expires on :date.',
                        ['tag' => $asset->asset_tag, 'name' => $asset->name, 'date' => $asset->warranty_expiry->toDateString()],
                        route('assets.show', $asset));
                }
                $asset->forceFill(['warranty_reminded_at' => now()])->saveQuietly();
            });
    }

    private function maintenance($horizon): void
    {
        $this->notRemindedToday(AssetMaintenance::query()->where('status', 'scheduled')->whereNotNull('scheduled_date')->whereDate('scheduled_date', '<=', $horizon))
            ->with('asset')->each(function (AssetMaintenance $job) {
                $recipients = $job->created_by ? collect([User::query()->find($job->created_by)]) : $this->usersWith('assets.update');
                foreach ($recipients->filter() as $user) {
                    $this->send($user, 'maintenance_due', 'Maintenance scheduled',
                        'Maintenance of :tag :name is scheduled for :date.',
                        ['tag' => (string) $job->asset?->asset_tag, 'name' => (string) $job->asset?->name, 'date' => $job->scheduled_date->toDateString()],
                        route('assets.show', $job->asset_id));
                }
                $job->forceFill(['reminded_at' => now()])->saveQuietly();
            });
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWith(string $permission): Collection
    {
        return User::query()->active()->get()->filter(fn (User $u) => $u->can($permission))->values();
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function send(?User $user, string $kind, string $title, string $body, array $replace, string $url): void
    {
        if ($user === null || ! $user->isActive()) {
            return;
        }

        Notification::send($user, new GenericNotification($kind, $title, $body, $replace, $url));
        $this->sent++;
    }
}
