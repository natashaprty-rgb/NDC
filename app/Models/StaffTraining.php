<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'provider', 'type', 'start_date', 'end_date', 'certificate_number', 'notes'])]
class StaffTraining extends Model
{
    use Auditable, HasFactory;

    /** Kinds of training and professional development => English labels. */
    public const TYPES = [
        'course' => 'Training course',
        'workshop' => 'Workshop',
        'certification' => 'Professional certification',
        'conference' => 'Conference or seminar',
        'degree' => 'Academic qualification',
        'on_the_job' => 'On-the-job training',
        'other' => 'Other',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function typeLabel(): string
    {
        return $this->type ? __(self::TYPES[$this->type] ?? $this->type) : '—';
    }
}
