<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Task rules:
 *  - assignees update progress and submit completion for their own tasks only;
 *  - creators / assigners / project managers / tasks.manage edit and reassign;
 *  - an assignee can never approve their own task (segregation of duties).
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // list is scoped to what the user may see (TaskController::visibleQuery)
    }

    public function view(User $user, Task $task): bool
    {
        return $user->hasPermission('tasks.view_all')
            || $this->isParticipant($user, $task)
            || $this->managesTask($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tasks.create');
    }

    public function update(User $user, Task $task): bool
    {
        return in_array($task->status, ['todo', 'in_progress', 'awaiting_approval'], true)
            && ($user->hasPermission('tasks.manage') || $this->managesTask($user, $task));
    }

    public function reassign(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /** Assignee updates status / progress / evidence of an open task. */
    public function updateProgress(User $user, Task $task): bool
    {
        return $task->assigned_to === $user->id && in_array($task->status, ['todo', 'in_progress'], true);
    }

    /** Assignee marks the task complete (or submits it for approval). */
    public function submit(User $user, Task $task): bool
    {
        return $this->updateProgress($user, $task);
    }

    public function approve(User $user, Task $task): bool
    {
        if ($task->status !== 'awaiting_approval' || $task->assigned_to === $user->id) {
            return false;
        }
        if ($task->approver_id !== null) {
            return $task->approver_id === $user->id;
        }

        return $user->hasPermission('tasks.approve')
            && ($user->hasPermission('tasks.manage') || $this->managesTask($user, $task));
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }

    public function attach(User $user, Task $task): bool
    {
        return $task->isOpen() && ($task->assigned_to === $user->id || $this->update($user, $task));
    }

    public function cancel(User $user, Task $task): bool
    {
        return $task->isOpen() && ($user->hasPermission('tasks.manage') || $this->managesTask($user, $task));
    }

    public function delete(User $user, Task $task): bool
    {
        return $task->status === 'todo' && ($user->hasPermission('tasks.manage') || $task->created_by === $user->id);
    }

    private function isParticipant(User $user, Task $task): bool
    {
        return in_array($user->id, array_filter([$task->assigned_to, $task->assigned_by, $task->created_by, $task->approver_id]), true);
    }

    /** Creator, assigner, project manager or head of the task's department. */
    private function managesTask(User $user, Task $task): bool
    {
        if ($task->created_by === $user->id || $task->assigned_by === $user->id) {
            return true;
        }
        if ($task->project_id && $task->project?->manager_id === $user->id) {
            return true;
        }

        return $task->department_id !== null && in_array($task->department_id, $user->headedDepartmentIds(), true);
    }
}
