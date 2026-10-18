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

class Task extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const STATUSES = ['todo', 'in_progress', 'awaiting_approval', 'completed', 'cancelled'];

    public const OPEN_STATUSES = ['todo', 'in_progress', 'awaiting_approval'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    protected $fillable = [
        'reference', 'project_id', 'milestone_id', 'department_id', 'title', 'description', 'deliverables',
        'priority', 'status', 'progress', 'start_date', 'due_date', 'assigned_to', 'assigned_by',
        'requires_approval', 'approver_id', 'approved_by', 'approved_at', 'completion_evidence',
        'review_note', 'submitted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'requires_approval' => 'boolean',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(ProjectMilestone::class, 'milestone_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by')->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id')->withTrashed();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by')->withTrashed();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest('id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->open()->whereNotNull('due_date')->whereDate('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true) && $this->due_date && $this->due_date->lt(today());
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
