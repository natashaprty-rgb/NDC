<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipant extends Model
{
    public const ATTENDANCE = ['invited', 'attended', 'absent', 'excused'];

    protected $fillable = ['meeting_id', 'user_id', 'external_name', 'external_organization', 'external_email', 'attendance'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function displayName(): string
    {
        return $this->user?->name ?? (string) $this->external_name;
    }
}
