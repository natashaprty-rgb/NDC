<?php

namespace App\Policies;

use App\Models\Correspondence;
use App\Models\User;

/**
 * Access to official letters. Confidential letters are limited to holders of
 * correspondence.view-confidential and to the people handling them
 * (Correspondence::isVisibleTo).
 */
class CorrespondencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('correspondence.view');
    }

    public function view(User $user, Correspondence $correspondence): bool
    {
        return $user->can('correspondence.view') && $correspondence->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can('correspondence.create');
    }

    public function update(User $user, Correspondence $correspondence): bool
    {
        return $user->can('correspondence.update') && $correspondence->isVisibleTo($user);
    }

    public function delete(User $user, Correspondence $correspondence): bool
    {
        return $user->can('correspondence.delete') && $correspondence->isVisibleTo($user);
    }

    /**
     * Assign the letter for action or response (sets officer and deadline).
     */
    public function assign(User $user, Correspondence $correspondence): bool
    {
        return $correspondence->allowsAction('assign') && $this->update($user, $correspondence);
    }

    /**
     * Forward, record a response or add a follow-up note: correspondence
     * officers, or the person the letter is currently assigned to.
     */
    public function handle(User $user, Correspondence $correspondence): bool
    {
        if (! $correspondence->allowsAction('handle') || ! $this->view($user, $correspondence)) {
            return false;
        }

        return $user->can('correspondence.update') || $correspondence->assigned_to === $user->id;
    }

    public function respond(User $user, Correspondence $correspondence): bool
    {
        return $correspondence->allowsAction('respond') && $this->handle($user, $correspondence);
    }

    public function markSent(User $user, Correspondence $correspondence): bool
    {
        return $correspondence->allowsAction('send') && $this->assign($user, $correspondence);
    }

    public function close(User $user, Correspondence $correspondence): bool
    {
        return $this->assign($user, $correspondence);
    }
}
