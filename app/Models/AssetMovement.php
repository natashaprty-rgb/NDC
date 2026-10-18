<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    public const ACTIONS = ['assigned', 'transferred', 'returned', 'retired'];

    protected $fillable = [
        'asset_id', 'action', 'from_staff_id', 'to_staff_id', 'from_location_id', 'to_location_id',
        'from_department_id', 'to_department_id', 'movement_date', 'notes', 'performed_by',
    ];

    protected function casts(): array
    {
        return ['movement_date' => 'date'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function fromStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'from_staff_id')->withTrashed();
    }

    public function toStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'to_staff_id')->withTrashed();
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id')->withTrashed();
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by')->withTrashed();
    }
}
