<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'body', 'is_evidence'])]
class TaskComment extends Model
{
    use HasAttachments;

    protected function casts(): array
    {
        return ['is_evidence' => 'boolean'];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Attachments on this record are authorised through its parent.
     */
    public function attachmentAuthorizationTarget(): Model
    {
        return $this->task;
    }
}
