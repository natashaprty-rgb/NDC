<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('budgets.view');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->hasPermission('budgets.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('budgets.manage');
    }

    /** Allocations may only change before approval. */
    public function update(User $user, Budget $budget): bool
    {
        return $user->hasPermission('budgets.manage') && $budget->isEditable();
    }

    public function submit(User $user, Budget $budget): bool
    {
        return $budget->status === 'draft' && $user->hasPermission('budgets.manage') && $budget->allocations()->exists();
    }

    /** The person who prepared the budget cannot approve it. */
    public function approve(User $user, Budget $budget): bool
    {
        return $budget->status === 'submitted'
            && $user->hasPermission('budgets.approve')
            && $budget->created_by !== $user->id;
    }

    public function close(User $user, Budget $budget): bool
    {
        return $budget->status === 'approved' && ($user->hasPermission('budgets.manage') || $user->hasPermission('budgets.approve'));
    }

    public function attach(User $user, Budget $budget): bool
    {
        return $user->hasPermission('budgets.manage');
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $budget->status === 'draft' && $user->hasPermission('budgets.manage') && ! $budget->expenses()->exists();
    }
}
