<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('staff.view');
    }

    public function view(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.view') || $staff->user_id === $user->id;
    }

    /** Employment details and notes. */
    public function viewSensitive(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.view_sensitive') || $user->hasPermission('staff.manage') || $staff->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('staff.manage');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.manage');
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $user->hasPermission('staff.manage');
    }
}
