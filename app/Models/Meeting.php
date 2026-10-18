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

class Meeting extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const TYPES = ['internal', 'management', 'board', 'project', 'external', 'workshop'];

    public const STATUSES = ['scheduled', 'completed', 'cancelled', 'postponed'];

    protected $fillable = [
        'reference', 'title', 'meeting_type', 'description', 'location', 'online_link', 'starts_at', 'ends_at',
        'status', 'organizer_id', 'department_id', 'project_id', 'agenda', 'minutes', 'minutes_recorded_by', 'minutes_recorded_at',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'minutes_recorded_at' => 'datetime'];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id')->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MeetingItem::class)->oldest('id');
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at');
    }
}
