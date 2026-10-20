<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('assets.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->can('assets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('assets.create');
    }

    /**
     * Editing details, attachments, assignments and maintenance.
     */
    public function update(User $user, Asset $asset): bool
    {
        return $user->can('assets.update');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->can('assets.delete') && $asset->status !== 'assigned';
    }

    /**
     * Raise a disposal or retirement request (approval is decided separately
     * by a holder of assets.approve-disposal).
     */
    public function requestDisposal(User $user, Asset $asset): bool
    {
        return $user->can('assets.update') && $asset->status !== 'disposed';
    }
}
