<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

/**
 * Task access mirrors Task::scopeVisibleTo(): without tasks.view-all a user
 * only reaches tasks assigned to them, created by them or assigned to their
 * department.
 *
 * Abilities:
 *  - "edit"   change the task definition (title, dates, priority, …): tasks.update;
 *  - "update" work on the task (progress, status as assignee, attachments,
 *             completion evidence): the assignee or a holder of tasks.update.
 *             AttachmentController authorises uploads with this ability.
 *  - "review" approve or reject submitted work: tasks.review, never the assignee.
 *
 * Status preconditions are checked here and again in the controllers, so a
 * change to the policy or to Gate::before can never skip the workflow rules.
 */
class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view') || $user->can('tasks.view-all');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->viewAny($user) && $task->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('tasks.create');
    }

    public function edit(User $user, Task $task): bool
    {
        return $user->can('tasks.update') && $this->view($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->view($user, $task) && ($task->isAssignedTo($user) || $user->can('tasks.update'));
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->can('tasks.delete') && $this->view($user, $task);
    }

    public function start(User $user, Task $task): bool
    {
        return $task->canStart() && $this->update($user, $task);
    }

    public function handIn(User $user, Task $task): bool
    {
        return $task->canHandIn() && $this->update($user, $task);
    }

    public function resume(User $user, Task $task): bool
    {
        return $task->canResume() && $this->update($user, $task);
    }

    public function progress(User $user, Task $task): bool
    {
        return $task->acceptsProgress() && $this->update($user, $task);
    }

    public function review(User $user, Task $task): bool
    {
        return $task->isAwaitingReview()
            && ! $task->isAssignedTo($user)
            && $user->can('tasks.review')
            && $this->view($user, $task);
    }

    public function reassign(User $user, Task $task): bool
    {
        return $task->isOpen() && $this->edit($user, $task);
    }

    public function cancel(User $user, Task $task): bool
    {
        return $task->isOpen() && $this->edit($user, $task);
    }

    public function reopen(User $user, Task $task): bool
    {
        return $task->canReopen() && $this->edit($user, $task);
    }

    public function comment(User $user, Task $task): bool
    {
        return $this->view($user, $task);
    }
}
