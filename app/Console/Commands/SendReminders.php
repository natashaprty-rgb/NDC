<?php

namespace App\Console\Commands;

use App\Models\Correspondence;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\SentReminder;
use App\Models\SupplierContract;
use App\Models\Task;
use App\Models\User;
use App\Services\ApprovalInbox;
use App\Services\Notifier;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Deadline, meeting, expiry and approval reminders. Safe to run as often as
 * hourly: every reminder is recorded in sent_reminders and is not repeated
 * (overdue reminders repeat at most weekly; the approval digest daily).
 */
class SendReminders extends Command
{
    protected $signature = 'ndc:send-reminders';

    protected $description = 'Send task, meeting, document, contract, correspondence and approval reminders';

    private int $sent = 0;

    public function handle(ApprovalInbox $inbox): int
    {
        $deadlineDays = (int) Settings::get('notifications.deadline_days_before', 2);
        $meetingHours = (int) Settings::get('notifications.meeting_hours_before', 24);
        $expiryDays = (int) Settings::get('notifications.expiry_days_before', 30);

        // Tasks due soon (once) and overdue (weekly)
        Task::query()->open()->whereNotNull('assigned_to')->whereNotNull('due_date')
            ->whereBetween('due_date', [today(), today()->addDays($deadlineDays)])->with('assignee')
            ->chunkById(200, function ($tasks) {
                foreach ($tasks as $task) {
                    $this->remindOnce('task_due_soon', $task, $task->assignee, 'reminders.task_due_soon', ['reference' => $task->reference, 'title' => $task->title, 'date' => fmt_date($task->due_date)], route('tasks.show', $task, false));
                }
            });
        Task::query()->overdue()->whereNotNull('assigned_to')->with(['assignee', 'assigner'])
            ->chunkById(200, function ($tasks) {
                foreach ($tasks as $task) {
                    foreach (array_filter([$task->assignee, $task->assigner]) as $user) {
                        $this->remindWeekly('task_overdue', $task, $user, 'reminders.task_overdue', ['reference' => $task->reference, 'title' => $task->title, 'date' => fmt_date($task->due_date)], route('tasks.show', $task, false));
                    }
                }
            });

        // Meetings starting soon (once per participant)
        Meeting::query()->where('status', 'scheduled')->whereBetween('starts_at', [now(), now()->addHours($meetingHours)])
            ->with(['participants.user', 'organizer'])->get()
            ->each(function (Meeting $meeting) {
                $users = $meeting->participants->pluck('user')->push($meeting->organizer)->filter()->unique('id');
                foreach ($users as $user) {
                    $this->remindOnce('meeting_upcoming', $meeting, $user, 'reminders.meeting_upcoming', ['title' => $meeting->title, 'when' => fmt_datetime($meeting->starts_at)], route('meetings.show', $meeting, false), forever: true);
                }
            });

        // Documents: review and expiry dates (once per document owner)
        Document::query()->whereNotIn('status', ['archived'])->whereNotNull('owner_id')
            ->where(fn ($q) => $q->whereBetween('expiry_date', [today(), today()->addDays($expiryDays)])->orWhereBetween('review_date', [today(), today()->addDays($expiryDays)]))
            ->with('owner')->get()
            ->each(function (Document $doc) use ($expiryDays) {
                if ($doc->expiry_date && $doc->expiry_date->between(today(), today()->addDays($expiryDays))) {
                    $this->remindOnce('document_expiry', $doc, $doc->owner, 'reminders.document_expiry', ['reference' => $doc->reference, 'title' => $doc->title, 'date' => fmt_date($doc->expiry_date)], route('documents.show', $doc, false), forever: true);
                }
                if ($doc->review_date && $doc->review_date->between(today(), today()->addDays($expiryDays))) {
                    $this->remindOnce('document_review', $doc, $doc->owner, 'reminders.document_review', ['reference' => $doc->reference, 'title' => $doc->title, 'date' => fmt_date($doc->review_date)], route('documents.show', $doc, false), forever: true);
                }
            });

        // Supplier contracts ending soon (once per contract manager)
        $contractManagers = User::query()->withPermission('suppliers.manage')->get();
        SupplierContract::query()->where('status', 'active')->whereBetween('end_date', [today(), today()->addDays($expiryDays)])->with('supplier')->get()
            ->each(function (SupplierContract $contract) use ($contractManagers) {
                foreach ($contractManagers as $user) {
                    $this->remindOnce('contract_expiry', $contract, $user, 'reminders.contract_expiry', ['reference' => $contract->reference, 'supplier' => $contract->supplier?->name, 'date' => fmt_date($contract->end_date)], route('contracts.show', $contract, false), forever: true);
                }
            });

        // Correspondence responses due (once) / overdue (weekly)
        Correspondence::query()->whereIn('status', Correspondence::OPEN_STATUSES)->whereNotNull('assigned_to')->whereNotNull('response_deadline')
            ->where('response_deadline', '<=', today()->addDays($deadlineDays))->with('assignee')
            ->chunkById(200, function ($items) {
                foreach ($items as $item) {
                    $params = ['reference' => $item->reference, 'subject' => $item->subject, 'date' => fmt_date($item->response_deadline)];
                    $url = route('correspondence.show', $item, false);
                    $item->response_deadline->lt(today())
                        ? $this->remindWeekly('correspondence_overdue', $item, $item->assignee, 'reminders.correspondence_overdue', $params, $url)
                        : $this->remindOnce('correspondence_due', $item, $item->assignee, 'reminders.correspondence_due', $params, $url, forever: true);
                }
            });

        // Daily digest of pending approvals
        $approvers = User::query()->active()->whereHas('roles.permissions', fn ($q) => $q->whereIn('slug', ['tasks.approve', 'documents.approve', 'procurement.approve', 'budgets.approve', 'expenses.approve']))->get();
        foreach ($approvers as $user) {
            $count = $inbox->count($user);
            if ($count > 0) {
                $this->remindOnce('approvals_pending', $user, $user, 'reminders.approvals_pending', ['count' => $count], route('approvals.index', [], false));
            }
        }

        Cache::forever('ndc.reminders.last_run', now());
        $this->info("Reminders sent: {$this->sent}");

        return self::SUCCESS;
    }

    /** Send once per day (or once ever when $forever). */
    private function remindOnce(string $type, Model $subject, ?User $user, string $key, array $params, string $url, bool $forever = false): void
    {
        if (! $user) {
            return;
        }
        $query = SentReminder::query()->where(['reminder_type' => $type, 'remindable_type' => $subject->getMorphClass(), 'remindable_id' => $subject->getKey(), 'user_id' => $user->id]);
        if (! $forever) {
            $query->whereDate('sent_on', today());
        }
        if ($query->exists()) {
            return;
        }
        $this->dispatch($type, $subject, $user, $key, $params, $url);
    }

    private function remindWeekly(string $type, Model $subject, ?User $user, string $key, array $params, string $url): void
    {
        if (! $user) {
            return;
        }
        $recent = SentReminder::query()->where(['reminder_type' => $type, 'remindable_type' => $subject->getMorphClass(), 'remindable_id' => $subject->getKey(), 'user_id' => $user->id])
            ->whereDate('sent_on', '>', today()->subDays(7))->exists();
        if (! $recent) {
            $this->dispatch($type, $subject, $user, $key, $params, $url);
        }
    }

    private function dispatch(string $type, Model $subject, User $user, string $key, array $params, string $url): void
    {
        $sent = Notifier::send($user, $type, $key, $params, $url, includeActor: true);
        if ($sent > 0) {
            SentReminder::query()->create(['reminder_type' => $type, 'remindable_type' => $subject->getMorphClass(), 'remindable_id' => $subject->getKey(), 'user_id' => $user->id, 'sent_on' => today()]);
            $this->sent++;
        }
    }
}
