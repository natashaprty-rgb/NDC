<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
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
