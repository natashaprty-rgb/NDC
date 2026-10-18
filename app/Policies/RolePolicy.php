<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.manage') || $user->hasPermission('users.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.manage');
    }

    /** The Super Administrator role always holds every permission and cannot be edited. */
    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage') && $role->slug !== 'super-admin';
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.manage') && ! $role->is_system && ! $role->users()->exists();
    }
}
