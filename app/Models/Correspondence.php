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
    'reference_number', 'direction', 'external_reference', 'subject', 'sender', 'recipient', 'letter_date',
    'received_or_sent_date', 'department_id', 'responsible_officer_id', 'assigned_to', 'status', 'priority',
    'is_confidential', 'response_due_date', 'summary', 'notes', 'parent_id', 'created_by',
])]
class Correspondence extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected array $auditExclude = ['reminded_at'];

    public const OPEN_STATUSES = ['pending', 'received', 'forwarded'];

    protected function casts(): array
    {
        return [
            'letter_date' => 'date',
            'received_or_sent_date' => 'date',
            'response_due_date' => 'date',
            'responded_at' => 'datetime',
            'closed_at' => 'datetime',
            'reminded_at' => 'datetime',
            'is_confidential' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function responsibleOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_officer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The letter this one replies to. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Correspondence::class, 'parent_id');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(CorrespondenceAction::class)->latest();
    }

    /**
     * Confidential letters are limited to users with
     * correspondence.view-confidential and the people handling them.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('correspondence.view-confidential')) {
            return;
        }

        $query->where(function (Builder $q) use ($user) {
            $q->where('is_confidential', false)
                ->orWhere('assigned_to', $user->id)
                ->orWhere('responsible_officer_id', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }

    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::OPEN_STATUSES);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->open()->whereNotNull('response_due_date')->whereDate('response_due_date', '<', today());
    }

    public function isVisibleTo(User $user): bool
    {
        return ! $this->is_confidential
            || $user->can('correspondence.view-confidential')
            || in_array($user->id, [$this->assigned_to, $this->responsible_officer_id, $this->created_by], true);
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Whether a workflow action is possible in the letter's current state
     * (independent of who is asking).
     */
    public function allowsAction(string $action): bool
    {
        if ($this->isClosed()) {
            return false;
        }

        return match ($action) {
            'respond' => $this->direction === 'incoming' && $this->status !== 'responded',
            'send' => $this->direction === 'outgoing' && $this->status !== 'sent',
            default => true,
        };
    }

    /**
     * Add an entry to the letter's follow-up history.
     */
    public function recordAction(string $action, ?User $by = null, ?int $toUserId = null, ?string $note = null, mixed $dueDate = null): CorrespondenceAction
    {
        /** @var CorrespondenceAction $entry */
        $entry = $this->actions()->create([
            'user_id' => $by?->id,
            'action' => $action,
            'to_user_id' => $toUserId,
            'note' => $note,
            'due_date' => $dueDate,
        ]);

        return $entry;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true)
            && $this->response_due_date !== null
            && $this->response_due_date->lt(today());
    }
}
