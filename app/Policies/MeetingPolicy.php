<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

/**
 * Meetings carry no confidentiality: anyone with meetings.view sees them.
 */
class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('meetings.view');
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $user->can('meetings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('meetings.create');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $user->can('meetings.update');
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->can('meetings.delete');
    }
}
