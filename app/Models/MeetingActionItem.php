<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A decision or action point recorded in meeting minutes.
 */
#[Fillable(['type', 'description', 'assigned_to', 'due_date', 'status', 'completed_at', 'notes'])]
class MeetingActionItem extends Model
{
    use Auditable, HasFactory;

    /** @var list<string> */
    protected array $auditExclude = ['reminded_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'completed_at' => 'datetime', 'reminded_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function scopeOutstanding(Builder $query): void
    {
        $query->whereIn('status', ['open', 'in_progress']);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->outstanding()->whereNotNull('due_date')->whereDate('due_date', '<', today());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['open', 'in_progress'], true)
            && $this->due_date !== null
            && $this->due_date->lt(today());
    }

    public function auditLabel(): string
    {
        return mb_substr($this->description, 0, 80);
    }
}
