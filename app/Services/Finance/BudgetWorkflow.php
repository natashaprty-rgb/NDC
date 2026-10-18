<?php

namespace App\Services\Finance;

use App\Models\Budget;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifier;
use App\Support\ReferenceNumber;
use Illuminate\Support\Facades\DB;

class BudgetWorkflow
{
    public function create(array $data): Budget
    {
        return DB::transaction(fn () => Budget::query()->create($data + [
            'reference' => ReferenceNumber::next('budget'),
            'status' => 'draft',
        ]));
    }

    public function submit(Budget $budget): void
    {
        $budget->update(['status' => 'submitted']);
        AuditLogger::log('submitted_for_approval', $budget, 'Budget submitted for approval');

        Notifier::send(User::query()->withPermission('budgets.approve')->get(), 'approval_pending', 'budgets.notify.submitted',
            ['reference' => $budget->reference, 'title' => $budget->title], route('budgets.show', $budget));
    }

    public function approve(Budget $budget, User $user): void
    {
        $budget->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
        AuditLogger::log('approved', $budget, 'Budget approved');

        Notifier::send($budget->creator, 'decision', 'budgets.notify.approved', ['reference' => $budget->reference], route('budgets.show', $budget));
    }

    /** Rejection returns the budget to draft so it can be corrected; the note is kept in the notes. */
    public function reject(Budget $budget, User $user, string $note): void
    {
        $entry = '['.now()->format('Y-m-d H:i').' '.__('budgets.rejected_by', ['name' => $user->name]).'] '.$note;
        $budget->update([
            'status' => 'draft',
            'notes' => trim(($budget->notes ? $budget->notes."\n\n" : '').$entry),
        ]);
        AuditLogger::log('rejected', $budget, 'Budget returned to draft: '.$note);

        Notifier::send($budget->creator, 'decision', 'budgets.notify.rejected', ['reference' => $budget->reference], route('budgets.show', $budget));
    }

    public function close(Budget $budget): void
    {
        $budget->update(['status' => 'closed']);
        AuditLogger::log('closed', $budget, 'Budget closed');
    }
}
