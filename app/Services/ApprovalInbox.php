<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Document;
use App\Models\Expense;
use App\Models\PurchaseRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Items currently waiting for a decision by the given user. Candidates are
 * pre-filtered in SQL and then confirmed with the same Policy ability the
 * approve action uses, so the inbox can never list something the user is
 * not allowed to decide.
 */
class ApprovalInbox
{
    private const LIMIT = 200;

    /** @return array<string, Collection> */
    public function for(User $user): array
    {
        $result = [
            'tasks' => collect(),
            'documents' => collect(),
            'purchase_requests' => collect(),
            'budgets' => collect(),
            'expenses' => collect(),
        ];

        $result['tasks'] = Task::query()->with(['project', 'assignee'])
            ->where('status', 'awaiting_approval')
            ->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', '!=', $user->id))
            ->orderBy('submitted_at')->limit(self::LIMIT)->get()
            ->filter(fn (Task $t) => Gate::forUser($user)->allows('approve', $t))->values();

        if ($user->hasPermission('documents.approve')) {
            $result['documents'] = Document::query()->with(['owner', 'category', 'accessGrants'])
                ->where('status', 'pending_review')->visibleTo($user)
                ->orderBy('updated_at')->limit(self::LIMIT)->get()
                ->filter(fn (Document $d) => Gate::forUser($user)->allows('approve', $d))->values();
        }

        if ($user->hasPermission('procurement.approve')) {
            $result['purchase_requests'] = PurchaseRequest::query()->with(['requester', 'department'])
                ->where('status', 'submitted')->where('requested_by', '!=', $user->id)
                ->orderBy('submitted_at')->limit(self::LIMIT)->get()
                ->filter(fn (PurchaseRequest $p) => Gate::forUser($user)->allows('approve', $p))->values();
        }

        if ($user->hasPermission('budgets.approve')) {
            $result['budgets'] = Budget::query()->with(['project', 'department'])
                ->where('status', 'submitted')
                ->orderBy('updated_at')->limit(self::LIMIT)->get()
                ->filter(fn (Budget $b) => Gate::forUser($user)->allows('approve', $b))->values();
        }

        if ($user->hasPermission('expenses.approve')) {
            $result['expenses'] = Expense::query()->with(['budget', 'category', 'recorder'])
                ->where('status', 'recorded')->where('recorded_by', '!=', $user->id)
                ->orderBy('expense_date')->limit(self::LIMIT)->get()
                ->filter(fn (Expense $e) => Gate::forUser($user)->allows('approve', $e))->values();
        }

        return $result;
    }

    public function count(User $user): int
    {
        return collect($this->for($user))->sum(fn (Collection $c) => $c->count());
    }
}
