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

class Correspondence extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const DIRECTIONS = ['incoming', 'outgoing'];

    public const STATUSES = ['registered', 'assigned', 'in_progress', 'responded', 'closed'];

    public const OPEN_STATUSES = ['registered', 'assigned', 'in_progress'];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const CONFIDENTIALITY = ['internal', 'confidential'];

    public const DELIVERY_METHODS = ['hand', 'post', 'email', 'courier', 'other'];

    protected $fillable = [
        'reference', 'direction', 'external_reference', 'subject', 'sender', 'recipient', 'letter_date',
        'received_or_sent_date', 'delivery_method', 'department_id', 'assigned_to', 'response_deadline',
        'priority', 'confidentiality', 'status', 'summary', 'in_reply_to_id', 'project_id', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'received_or_sent_date' => 'date',
            'response_deadline' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function inReplyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'in_reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'in_reply_to_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CorrespondenceAction::class)->latest('id');
    }

    /** Confidential items: assignee, creator, same department, or correspondence.view_confidential. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('correspondence.view_confidential')) {
            return $query;
        }
        $deptId = $user->departmentId();

        return $query->where(function (Builder $q) use ($user, $deptId) {
            $q->where('confidentiality', '!=', 'confidential')
                ->orWhere('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
            if ($deptId) {
                $q->orWhere('department_id', $deptId);
            }
        });
    }

    public function canBeViewedBy(User $user): bool
    {
        return $this->confidentiality !== 'confidential'
            || $user->hasPermission('correspondence.view_confidential')
            || $this->assigned_to === $user->id
            || $this->created_by === $user->id
            || ($this->department_id !== null && $this->department_id === $user->departmentId());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true) && $this->response_deadline && $this->response_deadline->lt(today());
    }
}
