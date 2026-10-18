<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

/**
 * recorded -> approved (approver != recorder) -> paid -> reconciled (settler != approver)
 * "Paid" and "reconciled" only record facts; no payment is executed by the system.
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasPermission('expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('expenses.record');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $expense->status === 'recorded' && $user->hasPermission('expenses.record') && $expense->recorded_by === $user->id;
    }

    public function approve(User $user, Expense $expense): bool
    {
        return $expense->status === 'recorded'
            && $user->hasPermission('expenses.approve')
            && $expense->recorded_by !== $user->id
            && $expense->created_by !== $user->id;
    }

    public function markPaid(User $user, Expense $expense): bool
    {
        return $expense->status === 'approved' && $user->hasPermission('expenses.settle') && $expense->approved_by !== $user->id;
    }

    public function reconcile(User $user, Expense $expense): bool
    {
        return $expense->status === 'paid' && $user->hasPermission('expenses.settle') && $expense->approved_by !== $user->id;
    }

    public function attach(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense) || ($user->hasPermission('expenses.settle') && in_array($expense->status, ['approved', 'paid'], true));
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }
}
