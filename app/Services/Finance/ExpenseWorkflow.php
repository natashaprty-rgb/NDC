<?php

namespace App\Services\Finance;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Expense;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Support\Money;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Expense lifecycle. Every step only RECORDS a fact; no payment is executed.
 *   recorded -> approved -> paid -> reconciled, or recorded -> rejected.
 */
class ExpenseWorkflow
{
    public function record(array $data, User $user): Expense
    {
        $expense = DB::transaction(function () use ($data, $user) {
            $budget = Budget::query()->findOrFail($data['budget_id']);

            return Expense::query()->create($data + [
                'reference' => ReferenceNumber::next('expense'),
                'currency' => $budget->currency,
                'status' => 'recorded',
                'recorded_by' => $user->id,
            ]);
        });

        Notifier::send(User::query()->withPermission('expenses.approve')->get(), 'approval_pending', 'expenses.notify.recorded',
            ['reference' => $expense->reference, 'amount' => money($expense->amount, $expense->currency)], route('expenses.show', $expense));

        return $expense;
    }

    /**
     * Approves an expense unless it would push committed spending
     * (approved + paid + reconciled) above the category allocation.
     * The allocation row is locked so concurrent approvals are serialised.
     */
    public function approve(Expense $expense, User $user): Expense
    {
        DB::transaction(function () use ($expense, $user) {
            $allocation = BudgetAllocation::query()
                ->where('budget_id', $expense->budget_id)
                ->where('expense_category_id', $expense->expense_category_id)
                ->lockForUpdate()
                ->first();

            $locked = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'recorded') {
                throw ValidationException::withMessages(['status' => __('expenses.not_pending')]);
            }
            if ($locked->budget?->status !== 'approved') {
                throw ValidationException::withMessages(['budget_id' => __('expenses.budget_not_approved')]);
            }
            if (! $allocation) {
                throw ValidationException::withMessages(['expense_category_id' => __('expenses.category_not_allocated')]);
            }

            $committed = Money::toCents((string) Expense::query()
                ->where('budget_id', $locked->budget_id)
                ->where('expense_category_id', $locked->expense_category_id)
                ->whereIn('status', Expense::COMMITTED_STATUSES)
                ->selectRaw('COALESCE(SUM(amount), 0) as total')
                ->value('total'));
            $allocated = Money::toCents((string) $allocation->amount);
            $amount = Money::toCents((string) $locked->amount);

            if ($committed + $amount > $allocated) {
                throw ValidationException::withMessages(['amount' => __('expenses.exceeds_allocation', [
                    'available' => money(Money::fromCents($allocated - $committed), $locked->currency),
                    'amount' => money($locked->amount, $locked->currency),
                ])]);
            }

            $locked->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            AuditLogger::log('approved', $locked, 'Expense approved');
            $expense->setRawAttributes($locked->getAttributes(), true);
        });

        Notifier::send($expense->recorder, 'decision', 'expenses.notify.approved', ['reference' => $expense->reference], route('expenses.show', $expense));

        return $expense;
    }

    public function reject(Expense $expense, User $user, string $note): Expense
    {
        DB::transaction(function () use ($expense, $user, $note) {
            $locked = Expense::query()->whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'recorded') {
                throw ValidationException::withMessages(['status' => __('expenses.not_pending')]);
            }
            $locked->update(['status' => 'rejected', 'rejected_by' => $user->id, 'rejected_at' => now(), 'review_note' => $note]);
            AuditLogger::log('rejected', $locked, 'Expense rejected: '.$note);
            $expense->setRawAttributes($locked->getAttributes(), true);
        });

        Notifier::send($expense->recorder, 'decision', 'expenses.notify.rejected', ['reference' => $expense->reference], route('expenses.show', $expense));

        return $expense;
    }

    public function markPaid(Expense $expense, User $user, string $paidOn, ?string $paymentReference): Expense
    {
        $expense->update([
            'status' => 'paid',
            'paid_on' => $paidOn,
            'payment_reference' => $paymentReference,
            'settled_by' => $user->id,
        ]);
        AuditLogger::log('paid_status', $expense, 'Payment recorded (made outside the system)');

        return $expense;
    }

    public function reconcile(Expense $expense, User $user): Expense
    {
        $expense->update(['status' => 'reconciled', 'reconciled_at' => now(), 'settled_by' => $user->id]);
        AuditLogger::log('reconciled', $expense, 'Expense reconciled');

        return $expense;
    }
}
