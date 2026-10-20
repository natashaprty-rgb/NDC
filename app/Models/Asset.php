<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'asset_tag', 'name', 'category_id', 'manufacturer', 'model', 'serial_number', 'supplier_id', 'purchase_date',
    'purchase_cost', 'currency', 'warranty_expiry', 'location', 'department_id', 'condition', 'specifications', 'notes',
    'created_by',
])]
class Asset extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    /** @var list<string> */
    protected array $auditExclude = ['warranty_reminded_at'];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_expiry' => 'date',
            'purchase_cost' => 'decimal:2',
            'specifications' => 'array',
            'warranty_reminded_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->latest('assigned_on')->latest('id');
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class)->latest('scheduled_date');
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class)->latest();
    }

    public function isUnderWarranty(): bool
    {
        return $this->warranty_expiry !== null && $this->warranty_expiry->gte(today());
    }
}
