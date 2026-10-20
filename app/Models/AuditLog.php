<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Append-only audit trail entry.
 */
#[Fillable(['user_id', 'event', 'auditable_type', 'auditable_id', 'description', 'old_values', 'new_values', 'ip_address', 'user_agent', 'url'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function subjectLabel(): string
    {
        if (! $this->auditable_type) {
            return '—';
        }

        return __(str($this->auditable_type)->replace('_', ' ')->title()->toString()).' #'.$this->auditable_id;
    }
}
