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

class PurchaseOrder extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const STATUSES = ['issued', 'partially_delivered', 'delivered', 'cancelled'];

    protected $fillable = [
        'reference', 'purchase_request_id', 'supplier_id', 'quotation_id', 'order_date', 'expected_delivery_date',
        'currency', 'total_amount', 'status', 'terms',
    ];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_delivery_date' => 'date'];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class)->latest('id');
    }
}
