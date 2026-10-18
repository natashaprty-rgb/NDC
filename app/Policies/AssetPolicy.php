<?php

namespace App\Policies;

use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('assets.view');
    }

    public function view(User $user, Asset $asset): bool
    {
        return $user->hasPermission('assets.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('assets.manage');
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->hasPermission('assets.manage') && ! $asset->isRetired();
    }

    /** Assign, transfer, return. */
    public function move(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }

    public function maintain(User $user, Asset $asset): bool
    {
        return $user->hasPermission('assets.manage');
    }

    public function retire(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }

    public function attach(User $user, Asset $asset): bool
    {
        return $user->hasPermission('assets.manage');
    }

    public function delete(User $user, Asset $asset): bool
    {
        return $user->hasPermission('assets.manage');
    }
}
