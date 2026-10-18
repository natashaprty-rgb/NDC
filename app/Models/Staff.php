<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use Auditable, HasFactory, HasUserstamps, SoftDeletes;

    protected $table = 'staff';

    public const EMPLOYMENT_STATUSES = ['active', 'on_leave', 'suspended', 'separated'];

    public const EMPLOYMENT_TYPES = ['permanent', 'contract', 'secondment', 'intern', 'consultant'];

    /** Fields shown only to users with staff.view_sensitive. */
    public const SENSITIVE_FIELDS = ['employment_status', 'employment_type', 'start_date', 'end_date', 'notes'];

    protected $fillable = [
        'staff_number', 'user_id', 'department_id', 'full_name', 'job_title', 'official_email',
        'official_phone', 'office_location', 'employment_status', 'employment_type', 'start_date', 'end_date', 'notes',
    ];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'assigned_staff_id');
    }
}
