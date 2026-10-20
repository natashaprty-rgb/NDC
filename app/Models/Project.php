<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'code', 'title', 'description', 'objectives', 'location', 'department_id', 'manager_id', 'status', 'priority',
    'start_date', 'expected_end_date', 'actual_end_date', 'planned_budget', 'currency', 'progress', 'created_by',
])]
class Project extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'actual_end_date' => 'date',
            'archived_at' => 'datetime',
            'planned_budget' => 'decimal:2',
            'progress' => 'integer',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('role')->withTimestamps();
    }

    public function stakeholders(): HasMany
    {
        return $this->hasMany(ProjectStakeholder::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('due_date');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class)->latest();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function expenses(): HasManyThrough
    {
        return $this->hasManyThrough(Expense::class, Budget::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /**
     * Not finished and past its expected completion date.
     */
    public function scopeDelayed(Builder $query): void
    {
        $query->whereNotIn('status', ['completed', 'cancelled'])
            ->whereNotNull('expected_end_date')
            ->whereDate('expected_end_date', '<', today());
    }

    public function isDelayed(): bool
    {
        return ! in_array($this->status, ['completed', 'cancelled'], true)
            && $this->expected_end_date !== null
            && $this->expected_end_date->lt(today());
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Sum of approved expenses recorded against this project's budgets.
     */
    public function recordedExpenditure(): float
    {
        return (float) $this->expenses()->where('expenses.status', 'approved')->sum('expenses.amount');
    }

    /**
     * id => "CODE — Title" pairs for select inputs.
     *
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::query()->active()->orderBy('title')->get()
            ->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->title])->all();
    }
}
