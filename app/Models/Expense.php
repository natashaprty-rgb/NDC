<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasAttachments;
use App\Support\Status;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Recorded expenditure against a budget. Only approved expenses count
 * towards budget utilisation.
 */
#[Fillable(['category_id', 'purchase_order_id', 'reference', 'description', 'payee', 'amount', 'expense_date', 'status', 'recorded_by'])]
class Expense extends Model
{
    use Auditable, HasApprovals, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date'];
    }

    /**
     * List filters shared by the expense register and the expenditure report.
     * Columns are qualified so the query can be joined to budgets for totals.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $date = fn ($value) => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
        $term = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';

        $query
            ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('expenses.description', 'like', "%{$term}%")
                ->orWhere('expenses.reference', 'like', "%{$term}%")
                ->orWhere('expenses.payee', 'like', "%{$term}%")))
            ->when((int) ($filters['budget'] ?? 0) > 0, fn (Builder $q) => $q->where('expenses.budget_id', (int) $filters['budget']))
            ->when((int) ($filters['category'] ?? 0) > 0, fn (Builder $q) => $q->where('expenses.category_id', (int) $filters['category']))
            ->when(in_array($filters['status'] ?? null, Status::keys('expense'), true), fn (Builder $q) => $q->where('expenses.status', $filters['status']))
            ->when($date($filters['from'] ?? null), fn (Builder $q, string $from) => $q->whereDate('expenses.expense_date', '>=', $from))
            ->when($date($filters['to'] ?? null), fn (Builder $q, string $to) => $q->whereDate('expenses.expense_date', '<=', $to))
            ->when((int) ($filters['project'] ?? 0) > 0, fn (Builder $q) => $q->whereHas('budget', fn (Builder $b) => $b->where('project_id', (int) $filters['project'])));
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Whether the expense's status permits an action (edit, withdraw, change
     * receipts). Checked in addition to the policy, because Super
     * Administrators bypass policies.
     */
    public function allows(string $action): bool
    {
        return in_array($action, ['update', 'delete'], true) ? $this->isPending() : true;
    }

    /**
     * Whether the signed-in user may perform the action now: the status allows
     * it and the policy grants it. Used by views to show or hide actions.
     */
    public function permits(string $action): bool
    {
        return $this->allows($action) && (bool) auth()->user()?->can($action, $this);
    }

    public function approvalDecided(Approval $approval): void
    {
        $this->forceFill(['status' => $approval->status === 'approved' ? 'approved' : 'rejected'])->save();
    }

    public function approvalTitle(): string
    {
        return __('Expense').': '.money($this->amount, $this->budget?->currency).' — '.$this->description;
    }

    public function approvalUrl(): string
    {
        return route('expenses.show', $this);
    }
}
