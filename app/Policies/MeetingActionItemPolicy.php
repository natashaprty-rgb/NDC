<?php

namespace App\Policies;

use App\Models\MeetingActionItem;
use App\Models\User;

class MeetingActionItemPolicy
{
    public function update(User $user, MeetingActionItem $item): bool
    {
        return $user->can('meetings.update');
    }

    public function delete(User $user, MeetingActionItem $item): bool
    {
        return $user->can('meetings.update');
    }

    /**
     * Progress updates: meeting administrators, or the person the action
     * point is assigned to (even without meetings.update).
     */
    public function updateStatus(User $user, MeetingActionItem $item): bool
    {
        return $user->can('meetings.update')
            || ($item->assigned_to !== null && $item->assigned_to === $user->id && $user->can('meetings.view'));
    }
}
