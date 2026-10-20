<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

/**
 * Staff profiles. Sensitive personal information and staff attachments (CVs,
 * certificates) need more than the directory permission.
 */
class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('staff.view');
    }

    public function view(User $user, Staff $staff): bool
    {
        return $user->can('staff.view');
    }

    public function create(User $user): bool
    {
        return $user->can('staff.create');
    }

    public function update(User $user, Staff $staff): bool
    {
        return $user->can('staff.update');
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $user->can('staff.delete');
    }

    /**
     * Date of birth, national ID, personal phone, address, emergency contact.
     */
    public function viewSensitive(User $user, ?Staff $staff = null): bool
    {
        return $user->can('staff.view-sensitive');
    }

    /**
     * CVs, certificates and other personal files attached to the profile.
     */
    public function viewAttachments(User $user, Staff $staff): bool
    {
        return $user->can('staff.update') || $user->can('staff.view-sensitive');
    }

    /**
     * Record, edit or remove training and professional development.
     */
    public function manageTrainings(User $user, Staff $staff): bool
    {
        return $user->can('staff.update');
    }
}
