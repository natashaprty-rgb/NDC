<?php

namespace App\Policies;

use App\Models\User;

/**
 * Only a Super Administrator may create, modify or grant the Super Administrator
 * role; nobody may suspend or remove their own account.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view') || $user->id === $target->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage') && (! $target->isSuperAdmin() || $user->isSuperAdmin());
    }

    public function assignRoles(User $user, User $target): bool
    {
        return $this->update($user, $target) && $user->id !== $target->id;
    }

    public function grantSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function suspend(User $user, User $target): bool
    {
        return $this->update($user, $target) && $user->id !== $target->id;
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $this->update($user, $target) && $user->id !== $target->id;
    }

    public function delete(User $user, User $target): bool
    {
        return $this->suspend($user, $target);
    }
}
