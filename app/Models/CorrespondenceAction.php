<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceAction extends Model
{
    public const ACTIONS = ['forwarded', 'follow_up', 'status_change', 'note'];

    protected $fillable = [
        'correspondence_id', 'action', 'from_user_id', 'to_user_id', 'to_department_id',
        'from_status', 'to_status', 'note', 'follow_up_date',
    ];

    protected function casts(): array
    {
        return ['follow_up_date' => 'date'];
    }

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id')->withTrashed();
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id')->withTrashed();
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id')->withTrashed();
    }
}
