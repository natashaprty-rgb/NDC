<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Follow-up history entry for a letter (assigned, forwarded, responded, ...).
 */
#[Fillable(['user_id', 'action', 'to_user_id', 'note', 'due_date'])]
class CorrespondenceAction extends Model
{
    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
