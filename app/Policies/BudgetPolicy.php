<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

/**
 * Budgets are visible to everyone holding budgets.view. Financial details
 * (amount, scope, period, allocations) can only change while the budget is a
 * draft or has been rejected; approval and closure require budgets.approve.
 *
 * Status rules live in Budget::allows() so controllers can enforce them for
 * Super Administrators too (they bypass policies through Gate::before).
 */
class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('budgets.view');
    }

    public function view(User $user, Budget $budget): bool
    {
        return $user->can('budgets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('budgets.create');
    }

    /**
     * Open the edit form and manage attachments. Approved budgets keep only
     * their descriptive fields (name, notes) editable.
     */
    public function update(User $user, Budget $budget): bool
    {
        return $user->can('budgets.update') && $budget->allows('update');
    }

    /**
     * Change the amount, scope, period and allocations.
     */
    public function changeAmounts(User $user, Budget $budget): bool
    {
        return $user->can('budgets.update') && $budget->allows('changeAmounts');
    }

    public function submit(User $user, Budget $budget): bool
    {
        return $budget->allows('submit')
            && ($user->can('budgets.update') || ($user->can('budgets.create') && (int) $budget->created_by === (int) $user->id));
    }

    public function close(User $user, Budget $budget): bool
    {
        return $user->can('budgets.approve') && $budget->allows('close');
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->can('budgets.delete') && $budget->allows('delete');
    }

    public function recordExpense(User $user, Budget $budget): bool
    {
        return $user->can('expenses.create') && $budget->allows('recordExpense');
    }
}
