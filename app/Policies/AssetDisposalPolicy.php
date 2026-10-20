<?php

namespace App\Policies;

use App\Models\AssetDisposal;
use App\Models\User;

class AssetDisposalPolicy
{
    public function view(User $user, AssetDisposal $disposal): bool
    {
        return $user->can('assets.view');
    }

    /**
     * Record that an approved disposal has been carried out.
     */
    public function complete(User $user, AssetDisposal $disposal): bool
    {
        return $user->can('assets.update') && $disposal->isAwaitingCompletion();
    }
}
