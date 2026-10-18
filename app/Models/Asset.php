<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const CONDITIONS = ['new', 'good', 'fair', 'poor', 'damaged'];

    public const STATUSES = ['in_stock', 'in_use', 'under_maintenance', 'retired', 'disposed', 'lost'];

    public const RETIRED_STATUSES = ['retired', 'disposed', 'lost'];

    protected $fillable = [
        'asset_tag', 'name', 'category_id', 'description', 'manufacturer', 'model', 'serial_number', 'purchase_date',
        'purchase_cost', 'currency', 'supplier_id', 'purchase_order_id', 'warranty_expiry', 'condition', 'status',
        'location_id', 'department_id', 'assigned_staff_id', 'specifications', 'retired_on', 'retirement_reason', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_expiry' => 'date',
            'retired_on' => 'date',
            'specifications' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withTrashed();
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id')->withTrashed();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->latest('id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class)->latest('id');
    }

    public function isRetired(): bool
    {
        return in_array($this->status, self::RETIRED_STATUSES, true);
    }
}
