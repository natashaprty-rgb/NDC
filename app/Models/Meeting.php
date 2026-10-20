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
    'title', 'type', 'description', 'start_at', 'end_at', 'location', 'organizer_id', 'department_id', 'project_id',
    'status', 'agenda', 'minutes', 'created_by',
])]
class Meeting extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected array $auditExclude = ['reminded_at'];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'minutes_recorded_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class)->orderBy('due_date');
    }

    public function minutesRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'minutes_recorded_by');
    }

    /**
     * Meetings that overlap the given period (used by the calendar).
     */
    public function scopeBetween(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): void
    {
        $query->where('start_at', '<=', $to)
            ->where(fn (Builder $q) => $q->where('start_at', '>=', $from)->orWhere('end_at', '>=', $from));
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('status', 'scheduled')->where('start_at', '>=', now())->orderBy('start_at');
    }
}
