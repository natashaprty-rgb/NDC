<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasUserstamps;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Budget extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const STATUSES = ['draft', 'submitted', 'approved', 'closed'];

    protected $fillable = [
        'reference', 'title', 'fiscal_year', 'period_start', 'period_end', 'project_id', 'department_id',
        'funding_source_id', 'currency', 'status', 'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'approved_at' => 'datetime', 'fiscal_year' => 'integer'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(BudgetAllocation::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Exact sum of allocations as a decimal string. */
    public function allocatedTotal(): string
    {
        return Money::fromCents(Money::toCents((string) ($this->allocations()->sum('amount') ?: '0')));
    }

    /**
     * Budget vs expenditure, in decimal strings. "recorded" includes every
     * non-rejected expense; "approved" counts approved+paid+reconciled;
     * "paid" counts paid+reconciled. Remaining balance = allocated - approved.
     *
     * @return array{allocated:string,recorded:string,approved:string,paid:string,reconciled:string,remaining:string,remaining_after_recorded:string}
     */
    public function summary(?int $categoryId = null): array
    {
        $allocQuery = $this->allocations();
        $expQuery = $this->expenses()->getQuery();
        if ($categoryId) {
            $allocQuery->where('expense_category_id', $categoryId);
            $expQuery->where('expense_category_id', $categoryId);
        }

        $byStatus = $expQuery->selectRaw('status, SUM(amount) as total')->groupBy('status')->pluck('total', 'status')
            ->map(fn ($v) => Money::toCents((string) $v));

        $allocated = Money::toCents((string) ($allocQuery->sum('amount') ?: '0'));
        $recorded = $byStatus->except(['rejected'])->sum();
        $approved = $byStatus->only(Expense::COMMITTED_STATUSES)->sum();
        $paid = $byStatus->only(['paid', 'reconciled'])->sum();

        return [
            'allocated' => Money::fromCents($allocated),
            'recorded' => Money::fromCents($recorded),
            'approved' => Money::fromCents($approved),
            'paid' => Money::fromCents($paid),
            'reconciled' => Money::fromCents($byStatus->get('reconciled', 0)),
            'remaining' => Money::fromCents($allocated - $approved),
            'remaining_after_recorded' => Money::fromCents($allocated - $recorded),
        ];
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'submitted'], true);
    }
}
