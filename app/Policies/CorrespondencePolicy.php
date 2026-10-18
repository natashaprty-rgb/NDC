<?php

namespace App\Policies;

use App\Models\Correspondence;
use App\Models\User;

class CorrespondencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('correspondence.view');
    }

    public function view(User $user, Correspondence $item): bool
    {
        return $user->hasPermission('correspondence.view') && $item->canBeViewedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('correspondence.manage');
    }

    public function update(User $user, Correspondence $item): bool
    {
        return $item->status !== 'closed' && $user->hasPermission('correspondence.manage') && $item->canBeViewedBy($user);
    }

    /** Forward, follow up, change status: registry officers or the assigned officer. */
    public function act(User $user, Correspondence $item): bool
    {
        return $this->view($user, $item)
            && ($user->hasPermission('correspondence.manage') || $item->assigned_to === $user->id)
            && ($item->status !== 'closed' || $user->hasPermission('correspondence.manage'));
    }

    public function attach(User $user, Correspondence $item): bool
    {
        return $this->act($user, $item);
    }

    public function delete(User $user, Correspondence $item): bool
    {
        return $user->hasPermission('correspondence.manage') && $item->canBeViewedBy($user);
    }
}
