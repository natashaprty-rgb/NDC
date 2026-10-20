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
    'user_id', 'staff_number', 'full_name', 'job_title', 'department_id', 'supervisor_id', 'email', 'phone',
    'employment_status', 'employment_type', 'hire_date', 'end_date', 'gender', 'date_of_birth', 'national_id',
    'personal_phone', 'address', 'emergency_contact', 'notes', 'created_by',
])]
class Staff extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    protected $table = 'staff';

    /**
     * Personal fields only visible with the staff.view-sensitive permission.
     */
    public const SENSITIVE = ['date_of_birth', 'national_id', 'personal_phone', 'address', 'emergency_contact'];

    /** @var list<string> */
    protected array $auditMask = self::SENSITIVE;

    /** Gender values => English labels (translated at render time). */
    public const GENDERS = ['female' => 'Female', 'male' => 'Male'];


    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'end_date' => 'date',
            'date_of_birth' => 'encrypted',
            'national_id' => 'encrypted',
            'personal_phone' => 'encrypted',
            'address' => 'encrypted',
            'emergency_contact' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'supervisor_id');
    }

    public function trainings(): HasMany
    {
        return $this->hasMany(StaffTraining::class)->orderByDesc('start_date');
    }

    /**
     * Search by name, staff number, job title or official e-mail.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }

        $like = "%{$term}%";
        $query->where(fn (Builder $q) => $q->where('full_name', 'like', $like)
            ->orWhere('staff_number', 'like', $like)
            ->orWhere('job_title', 'like', $like)
            ->orWhere('email', 'like', $like));
    }


    /**
     * id => "Name (staff number)" pairs for select inputs.
     *
     * @return array<int, string>
     */
    public static function options(?int $exceptId = null): array
    {
        return static::query()
            ->when($exceptId, fn (Builder $q) => $q->whereKeyNot($exceptId))
            ->orderBy('full_name')->get(['id', 'full_name', 'staff_number'])
            ->mapWithKeys(fn (Staff $s) => [$s->id => $s->full_name.' ('.$s->staff_number.')'])->all();
    }
}
