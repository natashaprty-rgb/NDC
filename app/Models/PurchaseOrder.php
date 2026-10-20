<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'po_number', 'supplier_id', 'quotation_id', 'order_date', 'expected_delivery_date', 'delivered_on',
    'total_amount', 'currency', 'status', 'contract_reference', 'contract_end_date', 'terms', 'created_by',
])]
class PurchaseOrder extends Model
{
    use Auditable, HasAttachments, HasFactory;

    /** @var list<string> */
    protected array $auditExclude = ['reminded_at'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'delivered_on' => 'date',
            'contract_end_date' => 'date',
            'total_amount' => 'decimal:2',
            'reminded_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class)->latest('received_on');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class);
    }

    /**
     * Attachments on a purchase order are authorised through its request.
     */
    public function attachmentAuthorizationTarget(): Model
    {
        return $this->request;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canReceiveGoods(): bool
    {
        return in_array($this->status, ['issued', 'partially_delivered'], true);
    }

    /**
     * Update the delivery status from the quantities received against the
     * request's items.
     */
    public function syncDeliveryStatus(?\DateTimeInterface $lastReceivedOn = null): void
    {
        $items = $this->request->items()->get();
        $received = $items->sum(fn (ProcurementRequestItem $i) => (float) $i->quantity_received);

        if ($received <= 0) {
            return;
        }

        $complete = $items->every(fn (ProcurementRequestItem $i) => $i->outstandingQuantity() <= 0);
        $this->forceFill([
            'status' => $complete ? 'delivered' : 'partially_delivered',
            'delivered_on' => $complete ? ($lastReceivedOn ?? today()) : null,
        ])->save();
    }
}
