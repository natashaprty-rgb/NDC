<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermissionReview extends Model
{
    use Auditable;

    protected $fillable = ['reviewed_by', 'review_date', 'accounts_reviewed', 'findings', 'actions_taken', 'next_review_due'];

    protected function casts(): array
    {
        return ['review_date' => 'date', 'next_review_due' => 'date'];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }
}
