<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Support\ReferenceNumber;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /**
     * The assignment that has not been returned yet, if any.
     */
    public function openAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_on')->latest('id');
    }

    /**
     * Disposal or retirement request that is still pending or approved but
     * not yet completed.
     */
    public function openDisposal(): HasOne
    {
        return $this->hasOne(AssetDisposal::class)->whereIn('status', ['pending', 'approved'])->latest('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Assets whose warranty is still valid but ends within the given number of days.
     */
    public function scopeWarrantyExpiringWithin(Builder $query, int $days): void
    {
        $query->whereNotNull('warranty_expiry')
            ->whereDate('warranty_expiry', '>=', today())
            ->whereDate('warranty_expiry', '<=', today()->addDays($days));
    }

    /**
     * Retired and disposed assets are no longer in service.
     */
    public function isInService(): bool
    {
        return ! in_array($this->status, ['retired', 'disposed'], true);
    }

    public function warrantyExpiresWithin(int $days): bool
    {
        return $this->isUnderWarranty() && $this->warranty_expiry->lte(today()->addDays($days));
    }

    /**
     * Derive the operational status from open maintenance, the current
     * assignment and the recorded condition. Retired and disposed assets are
     * left unchanged.
     */
    public function settleStatus(): void
    {
        if (! $this->isInService()) {
            return;
        }

        $status = match (true) {
            $this->maintenances()->where('status', 'in_progress')->exists() => 'under_maintenance',
            $this->assigned_to !== null || $this->openAssignment()->exists() => 'assigned',
            $this->condition === 'damaged' => 'damaged',
            default => 'available',
        };

        if ($this->status !== $status) {
            $this->forceFill(['status' => $status])->save();
        }
    }

    /**
     * Next free asset tag, e.g. NDC/AST/2026/0001.
     */
    public static function nextTag(): string
    {
        do {
            $tag = ReferenceNumber::next('AST');
        } while (static::withTrashed()->where('asset_tag', $tag)->exists());

        return $tag;
    }
}
