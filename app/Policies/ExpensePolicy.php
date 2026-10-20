<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * Expenses are read with budgets.view. A pending expense may be corrected or
 * withdrawn by the person who recorded it, or by a budget editor; once decided
 * it is locked.
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('budgets.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('budgets.view');
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    /**
     * Also governs uploading and removing receipts.
     */
    public function update(User $user, Expense $expense): bool
    {
        return $expense->allows('update') && $this->ownsOrManages($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $expense->allows('delete') && $this->ownsOrManages($user, $expense);
    }

    private function ownsOrManages(User $user, Expense $expense): bool
    {
        return $user->can('budgets.update')
            || ($user->can('expenses.create') && (int) $expense->recorded_by === (int) $user->id);
    }
}
