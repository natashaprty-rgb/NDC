<?php

namespace App\Policies;

use App\Models\SupplierContract;
use App\Models\User;

class SupplierContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('suppliers.view');
    }

    public function view(User $user, SupplierContract $contract): bool
    {
        return $user->hasPermission('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('suppliers.manage');
    }

    public function update(User $user, SupplierContract $contract): bool
    {
        return $user->hasPermission('suppliers.manage');
    }

    public function attach(User $user, SupplierContract $contract): bool
    {
        return $user->hasPermission('suppliers.manage');
    }

    public function delete(User $user, SupplierContract $contract): bool
    {
        return $user->hasPermission('suppliers.manage');
    }
}
