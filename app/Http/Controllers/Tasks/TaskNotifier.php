<?php

namespace App\Http\Controllers\Tasks;

use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the task module's in-app (and, when enabled, e-mail) notifications.
 * The person who performed the action is never notified about it.
 */
final class TaskNotifier
{
    /**
     * New assignment: the assignee, or the head of the department when the
     * task is assigned to a department only.
     */
    public function assigned(Task $task, User $actor): void
    {
        $task->loadMissing(['assignee', 'department.head']);
        $recipient = $task->assignee ?? $task->department?->head;

        $this->send(collect([$recipient]), $actor, 'task_assigned', 'New task assigned',
            'You have been assigned the task ":title".', $task);
    }

    public function reassigned(Task $task, User $actor): void
    {
        $task->load(['assignee', 'department.head']);
        $recipient = $task->assignee ?? $task->department?->head;

        $this->send(collect([$recipient]), $actor, 'task_reassigned', 'Task reassigned to you',
            'The task ":title" has been reassigned to you.', $task);
    }

    public function handedIn(Task $task, User $actor): void
    {
        [$kind, $title, $body] = $task->status === 'submitted'
            ? ['task_submitted', 'Task submitted for review', 'The task ":title" has been submitted for review.']
            : ['task_completed', 'Task completed', 'The task ":title" has been marked as completed.'];

        $this->send(collect([$task->creator]), $actor, $kind, $title, $body, $task);
    }

    public function reviewed(Task $task, User $actor): void
    {
        [$kind, $title, $body] = $task->status === 'completed'
            ? ['task_approved', 'Task approved', 'Your work on the task ":title" has been approved.']
            : ['task_rejected', 'Task returned for rework', 'The task ":title" has been returned to you. Reason: :reason'];

        $this->send(collect([$task->assignee]), $actor, $kind, $title, $body, $task, ['reason' => (string) $task->review_comment]);
    }

    public function cancelled(Task $task, User $actor): void
    {
        $this->send(collect([$task->assignee]), $actor, 'task_cancelled', 'Task cancelled',
            'The task ":title" has been cancelled.', $task);
    }

    public function reopened(Task $task, User $actor): void
    {
        $this->send(collect([$task->assignee]), $actor, 'task_reopened', 'Task reopened',
            'The task ":title" has been reopened.', $task);
    }

    public function commented(Task $task, User $actor): void
    {
        $this->send(collect([$task->assignee, $task->creator]), $actor, 'task_comment', 'New comment on a task',
            ':name commented on the task ":title".', $task, ['name' => $actor->name]);
    }

    /**
     * @param  Collection<int, User|null>  $recipients
     * @param  array<string, string>  $replace
     */
    private function send(Collection $recipients, User $actor, string $kind, string $title, string $body, Task $task, array $replace = []): void
    {
        $recipients = $recipients->filter()->unique('id')
            ->reject(fn (User $user) => $user->is($actor) || ! $user->isActive());

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new GenericNotification(
            $kind, $title, $body, ['title' => $task->title, ...$replace], route('tasks.show', $task),
        ));
    }
}
