<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('procurement.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('procurement.view');
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.manage');
    }

    /**
     * Editing details, attachments and performance evaluations.
     */
    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.manage');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.manage');
    }
}
