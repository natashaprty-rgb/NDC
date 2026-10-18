<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingItem extends Model
{
    use Auditable, HasUserstamps;

    public const TYPES = ['decision', 'action'];

    public const STATUSES = ['open', 'done', 'cancelled'];

    protected $fillable = ['meeting_id', 'type', 'description', 'responsible_id', 'due_date', 'status', 'task_id'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class)->withTrashed();
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id')->withTrashed();
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
