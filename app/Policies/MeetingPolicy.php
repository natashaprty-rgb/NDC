<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('meetings.view');
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $user->hasPermission('meetings.view')
            || $meeting->organizer_id === $user->id
            || $meeting->participants->contains('user_id', $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('meetings.manage');
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $user->hasPermission('meetings.manage') || $meeting->organizer_id === $user->id;
    }

    /** Minutes, decisions and action points. */
    public function recordMinutes(User $user, Meeting $meeting): bool
    {
        return $meeting->status !== 'cancelled' && $this->update($user, $meeting);
    }

    public function attach(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $user->hasPermission('meetings.manage');
    }
}
