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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Approved budget for a project, a department or a funding source. Used for
 * monitoring only; no payments are made by the system.
 */
#[Fillable([
    'code', 'name', 'fiscal_year', 'project_id', 'department_id', 'funding_source_id', 'amount', 'currency',
    'start_date', 'end_date', 'status', 'notes', 'created_by',
])]
class Budget extends Model
{
    use Auditable, HasApprovals, HasAttachments, HasFactory, SoftDeletes;

    /** Statuses in which the amount, scope and allocations may still change. */
    public const EDITABLE_STATUSES = ['draft', 'rejected'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * List filters shared by the budget register and the budget summary report.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query
            ->when(is_string($filters['q'] ?? null) && trim($filters['q']) !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('budgets.code', 'like', '%'.trim($filters['q']).'%')
                ->orWhere('budgets.name', 'like', '%'.trim($filters['q']).'%')))
            ->when(is_string($filters['fiscal_year'] ?? null) && $filters['fiscal_year'] !== '', fn (Builder $q) => $q->where('budgets.fiscal_year', $filters['fiscal_year']))
            ->when((int) ($filters['project'] ?? 0) > 0, fn (Builder $q) => $q->where('budgets.project_id', (int) $filters['project']))
            ->when((int) ($filters['department'] ?? 0) > 0, fn (Builder $q) => $q->where('budgets.department_id', (int) $filters['department']))
            ->when((int) ($filters['funding_source'] ?? 0) > 0, fn (Builder $q) => $q->where('budgets.funding_source_id', (int) $filters['funding_source']))
            ->when(in_array($filters['status'] ?? null, Status::keys('budget'), true), fn (Builder $q) => $q->where('budgets.status', $filters['status']));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'funding_source_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->latest('expense_date');
    }

    public function plannedTotal(): float
    {
        return (float) $this->allocations()->sum('planned_amount');
    }

    public function approvedSpend(): float
    {
        return (float) $this->expenses()->where('status', 'approved')->sum('amount');
    }

    public function pendingSpend(): float
    {
        return (float) $this->expenses()->where('status', 'pending')->sum('amount');
    }

    public function remaining(): float
    {
        return (float) $this->amount - $this->approvedSpend();
    }

    public function utilisation(): int
    {
        return (float) $this->amount > 0 ? (int) round($this->approvedSpend() / (float) $this->amount * 100) : 0;
    }

    /**
     * Whether the amount, scope, period and allocations may still be changed.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, self::EDITABLE_STATUSES, true);
    }

    /**
     * Whether the budget's current status permits a workflow action. Checked
     * in addition to the policy, because Super Administrators bypass policies.
     */
    public function allows(string $action): bool
    {
        return match ($action) {
            'update' => in_array($this->status, ['draft', 'rejected', 'approved'], true),
            'changeAmounts', 'submit', 'delete' => $this->isEditable(),
            'close' => $this->status === 'approved',
            'recordExpense' => $this->acceptsExpenses(),
            default => true,
        };
    }

    /**
     * Whether the signed-in user may perform the action now: the status allows
     * it and the policy grants it. Used by views to show or hide actions.
     */
    public function permits(string $action): bool
    {
        return $this->allows($action) && (bool) auth()->user()?->can($action, $this);
    }

    /**
     * Expenses can only be recorded against an approved budget that is not closed.
     */
    public function acceptsExpenses(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Convert a decimal money value to integer cents so totals are compared
     * without floating-point drift.
     */
    public static function cents(float|int|string|null $value): int
    {
        return (int) round((float) $value * 100);
    }

    /**
     * Amount, planned total, approved and pending spend, remaining balance and
     * utilisation, computed with two aggregate queries.
     *
     * @return array{amount: float, planned: float, approved: float, pending: float, committed: float, remaining: float, utilisation: int, over_committed: bool, over_spent: bool}
     */
    public function spendSummary(): array
    {
        $byStatus = $this->expenses()->reorder()
            ->selectRaw('status, SUM(amount) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $amount = self::cents($this->amount);
        $approved = self::cents($byStatus['approved'] ?? 0);
        $pending = self::cents($byStatus['pending'] ?? 0);
        $planned = self::cents($this->allocations()->sum('planned_amount'));

        return [
            'amount' => $amount / 100,
            'planned' => $planned / 100,
            'approved' => $approved / 100,
            'pending' => $pending / 100,
            'committed' => ($approved + $pending) / 100,
            'remaining' => ($amount - $approved) / 100,
            'utilisation' => $amount > 0 ? (int) round($approved / $amount * 100) : 0,
            'over_committed' => $approved + $pending > $amount,
            'over_spent' => $approved > $amount,
        ];
    }

    /**
     * Progress-bar tone for a utilisation percentage.
     */
    public static function utilisationTone(int $percent): ?string
    {
        return match (true) {
            $percent > 100 => 'danger',
            $percent >= 80 => 'warn',
            default => null,
        };
    }

    /**
     * Planned (allocations) against actual (approved expenses) per expense
     * category. Pending spend is shown separately and rejected expenses are
     * ignored.
     *
     * @return Collection<int, array{category: string, planned: float, approved: float, pending: float, variance: float, utilisation: ?int}>
     */
    public function categoryBreakdown(): Collection
    {
        $planned = $this->allocations()->reorder()
            ->selectRaw('category_id, SUM(planned_amount) as total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $spent = $this->expenses()->reorder()
            ->whereIn('status', ['approved', 'pending'])
            ->selectRaw('category_id, status, SUM(amount) as total')
            ->groupBy('category_id', 'status')
            ->get();

        // Null category ids (uncategorised rows) are normalised to ''.
        $ids = $planned->keys()->merge($spent->pluck('category_id'))
            ->map(fn ($id) => (string) $id)->unique()->values();
        $names = Category::query()->whereIn('id', $ids->filter(fn ($id) => $id !== ''))->get()
            ->mapWithKeys(fn (Category $c) => [$c->id => $c->displayName()]);

        return $ids->map(function ($id) use ($planned, $spent, $names) {
            $plannedCents = self::cents($planned[$id] ?? 0);
            $rows = $spent->filter(fn ($row) => (string) $row->category_id === $id);
            $approvedCents = self::cents($rows->firstWhere('status', 'approved')?->total ?? 0);
            $pendingCents = self::cents($rows->firstWhere('status', 'pending')?->total ?? 0);

            return [
                'category' => $id !== '' ? ($names[(int) $id] ?? __('Unknown category')) : __('Uncategorised'),
                'planned' => $plannedCents / 100,
                'approved' => $approvedCents / 100,
                'pending' => $pendingCents / 100,
                'variance' => ($plannedCents - $approvedCents) / 100,
                'utilisation' => $plannedCents > 0 ? (int) round($approvedCents / $plannedCents * 100) : null,
            ];
        })->sortBy('category', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function approvalDecided(Approval $approval): void
    {
        $this->forceFill(['status' => $approval->status === 'approved' ? 'approved' : 'rejected'])->save();
    }

    public function approvalTitle(): string
    {
        return __('Budget').': '.$this->code.' — '.$this->name;
    }

    public function approvalUrl(): string
    {
        return route('budgets.show', $this);
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::query()->where('status', 'approved')->orderBy('code')->get()
            ->mapWithKeys(fn (Budget $b) => [$b->id => $b->code.' — '.$b->name])->all();
    }
}
