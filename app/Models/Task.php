<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'project_id', 'milestone_id', 'title', 'description', 'expected_deliverable', 'assigned_to', 'department_id',
    'created_by', 'priority', 'status', 'progress', 'start_date', 'due_date', 'requires_review',
])]
class Task extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected array $auditExclude = ['reminded_at'];

    public const OPEN_STATUSES = ['pending', 'in_progress', 'submitted', 'rejected'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'requires_review' => 'boolean',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminded_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest();
    }

    /**
     * Record-level visibility: users without tasks.view-all only see tasks
     * assigned to them, created by them, or assigned to their department.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('tasks.view-all')) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('assigned_to', $user->id)
                ->orWhere('created_by', $user->id)
                ->when($user->department_id, fn (Builder $q) => $q->orWhere('tasks.department_id', $user->department_id));
        });
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('due_date')->whereDate('due_date', '<', today());
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_date !== null && $this->due_date->lt(today());
    }

    /**
     * Mirror of scopeVisibleTo() for a single loaded record.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->can('tasks.view-all')) {
            return true;
        }

        return $this->isAssignedTo($user)
            || ($this->created_by !== null && (int) $this->created_by === (int) $user->id)
            || ($user->department_id !== null && $this->department_id !== null
                && (int) $this->department_id === (int) $user->department_id);
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_to !== null && (int) $this->assigned_to === (int) $user->id;
    }

    /**
     * Status the assignee moves the task to when handing in the work.
     */
    public function handInStatus(): string
    {
        return $this->requires_review ? 'submitted' : 'completed';
    }

    public function canStart(): bool
    {
        return $this->status === 'pending';
    }

    public function canHandIn(): bool
    {
        return $this->status === 'in_progress';
    }

    public function canResume(): bool
    {
        return $this->status === 'rejected';
    }

    public function isAwaitingReview(): bool
    {
        return $this->status === 'submitted';
    }

    /**
     * Progress can be reported while the work is with the assignee.
     */
    public function acceptsProgress(): bool
    {
        return in_array($this->status, ['pending', 'in_progress', 'rejected'], true);
    }

    public function canReopen(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }
}
