<?php

namespace App\Services\Projects;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FileStorage;
use App\Services\Notifier;
use App\Support\ReferenceNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Task workflow: create / reassign / progress / submit / approve / reject / cancel.
 * Authorisation is checked by the controller (TaskPolicy) before calling these methods.
 */
class TaskService
{
    public function create(array $data, User $actor): Task
    {
        $task = DB::transaction(function () use ($data, $actor) {
            $data['reference'] = ReferenceNumber::next('task');
            $data['status'] = 'todo';
            $data['progress'] = 0;
            $data['assigned_by'] = ! empty($data['assigned_to']) ? $actor->id : null;

            return Task::query()->create($data);
        });

        $this->notifyAssignee($task);

        return $task;
    }

    public function update(Task $task, array $data, User $actor): Task
    {
        $previousAssignee = $task->assigned_to;
        $newAssignee = $data['assigned_to'] ?? null;
        $reassigned = (int) $previousAssignee !== (int) $newAssignee;

        DB::transaction(function () use ($task, $data, $actor, $reassigned, $previousAssignee, $newAssignee) {
            if ($reassigned) {
                $data['assigned_by'] = $newAssignee ? $actor->id : null;
            }
            $task->update($data);

            if ($reassigned) {
                AuditLogger::log('reassigned', $task, 'Task reassigned', ['assigned_to' => $previousAssignee], ['assigned_to' => $newAssignee]);
            }
        });

        if ($reassigned) {
            $this->notifyAssignee($task->fresh());
        }

        return $task;
    }

    public function updateProgress(Task $task, int $progress, string $status): void
    {
        $task->update(['progress' => $progress, 'status' => $status]);
    }

    public function submit(Task $task, string $evidence, ?UploadedFile $file): void
    {
        DB::transaction(function () use ($task, $evidence, $file) {
            if ($file) {
                FileStorage::attach($task, $file, 'evidence', 'evidence_file');
            }

            if ($task->requires_approval) {
                $task->update([
                    'completion_evidence' => $evidence,
                    'status' => 'awaiting_approval',
                    'submitted_at' => now(),
                    'progress' => 100,
                ]);
                AuditLogger::log('submitted_for_approval', $task, 'Task completion submitted for approval');
            } else {
                $task->update([
                    'completion_evidence' => $evidence,
                    'status' => 'completed',
                    'submitted_at' => now(),
                    'completed_at' => now(),
                    'progress' => 100,
                ]);
            }
        });

        if ($task->status === 'awaiting_approval') {
            Notifier::send($this->approvalRecipients($task), 'approval_pending', 'tasks.notify.submitted', $this->params($task), route('tasks.show', $task));
        }
    }

    public function approve(Task $task, User $actor, ?string $note): void
    {
        DB::transaction(function () use ($task, $actor, $note) {
            $task->update([
                'status' => 'completed',
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'completed_at' => now(),
                'progress' => 100,
                'review_note' => $note,
            ]);
            AuditLogger::log('approved', $task, 'Task completion approved');
        });

        Notifier::send($task->assignee, 'task_approved', 'tasks.notify.approved', $this->params($task), route('tasks.show', $task));
    }

    public function reject(Task $task, string $note): void
    {
        DB::transaction(function () use ($task, $note) {
            $task->update([
                'status' => 'in_progress',
                'review_note' => $note,
                'submitted_at' => null,
            ]);
            AuditLogger::log('rejected', $task, 'Task completion rejected: '.$note);
        });

        Notifier::send($task->assignee, 'task_rejected', 'tasks.notify.rejected', $this->params($task), route('tasks.show', $task));
    }

    public function cancel(Task $task, string $reason): void
    {
        $task->update(['status' => 'cancelled', 'review_note' => $reason]);
        AuditLogger::log('cancelled', $task, 'Task cancelled: '.$reason);
    }

    public function comment(Task $task, User $actor, string $body): TaskComment
    {
        $comment = $task->comments()->create(['user_id' => $actor->id, 'body' => $body]);

        $recipients = User::query()->whereIn('id', array_filter([$task->assigned_to, $task->created_by]))->get();
        Notifier::send($recipients, 'task_comment', 'tasks.notify.comment', $this->params($task), route('tasks.show', $task));

        return $comment;
    }

    private function notifyAssignee(Task $task): void
    {
        if ($task->assigned_to) {
            Notifier::send($task->assignee, 'task_assigned', 'tasks.notify.assigned', $this->params($task), route('tasks.show', $task));
        }
    }

    /** Designated approver, otherwise creator / assigner / project manager — never the assignee. */
    private function approvalRecipients(Task $task)
    {
        $ids = $task->approver_id
            ? [$task->approver_id]
            : array_filter([$task->created_by, $task->assigned_by, $task->project?->manager_id]);

        return User::query()->whereIn('id', array_diff(array_unique($ids), [$task->assigned_to]))->get();
    }

    private function params(Task $task): array
    {
        return ['reference' => $task->reference, 'title' => $task->title];
    }
}
