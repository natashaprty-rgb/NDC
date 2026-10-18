<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const STATUSES = ['planned', 'in_progress', 'on_hold', 'completed', 'cancelled'];

    public const ACTIVE_STATUSES = ['planned', 'in_progress', 'on_hold'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'reference', 'name', 'description', 'objectives', 'status', 'priority', 'start_date',
        'expected_end_date', 'actual_end_date', 'manager_id', 'department_id', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'actual_end_date' => 'date',
            'archived_at' => 'datetime',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id')->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
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
        return $this->hasMany(ProjectRisk::class);
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

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    public function scopeNotArchived(Builder $q): Builder
    {
        return $q->whereNull('archived_at');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereNull('archived_at')->whereIn('status', ['in_progress']);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->expected_end_date !== null
            && in_array($this->status, self::ACTIVE_STATUSES, true)
            && $this->expected_end_date->lt(today());
    }

    /**
     * Progress is derived from recorded work, never typed in:
     * completed tasks / non-cancelled tasks; falls back to milestones.
     */
    public function progressPercent(): int
    {
        $tasks = $this->tasks()->where('status', '!=', 'cancelled')
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as done")->first();
        if ((int) $tasks->total > 0) {
            return (int) floor(((int) $tasks->done) * 100 / (int) $tasks->total);
        }

        $ms = $this->milestones()->getQuery()->reorder()
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as done")->first();

        return (int) $ms->total > 0 ? (int) floor(((int) $ms->done) * 100 / (int) $ms->total) : 0;
    }
}
