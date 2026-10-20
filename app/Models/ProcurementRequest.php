<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasApprovals;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Purchase request. Lifecycle: draft -> submitted -> approved/rejected ->
 * ordered -> partially_received -> received (or cancelled).
 */
#[Fillable([
    'reference_number', 'title', 'justification', 'department_id', 'project_id', 'budget_id', 'requested_by',
    'needed_by', 'status', 'estimated_total', 'currency',
])]
class ProcurementRequest extends Model
{
    use Auditable, HasApprovals, HasAttachments, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'needed_by' => 'date',
            'submitted_at' => 'datetime',
            'estimated_total' => 'decimal:2',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProcurementRequestItem::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->orderBy('amount');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)->latest('order_date');
    }

    public function recalculateTotal(): void
    {
        $total = $this->items()->get()->sum(fn (ProcurementRequestItem $i) => (float) $i->quantity * (float) $i->estimated_unit_cost);
        $this->forceFill(['estimated_total' => $total])->save();
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'rejected'], true);
    }

    public function approvalDecided(Approval $approval): void
    {
        $this->forceFill(['status' => $approval->status === 'approved' ? 'approved' : 'rejected'])->save();
    }

    public function approvalTitle(): string
    {
        return __('Purchase request').': '.$this->reference_number.' — '.$this->title;
    }

    public function approvalUrl(): string
    {
        return route('procurement.show', $this);
    }

    public function selectedQuotation(): HasOne
    {
        return $this->hasOne(Quotation::class)->where('is_selected', true);
    }

    /**
     * The purchase order currently in force (cancelled orders are ignored).
     */
    public function activePurchaseOrder(): HasOne
    {
        return $this->hasOne(PurchaseOrder::class)->where('status', '!=', 'cancelled')->latest('id');
    }

    /**
     * Quotations and purchase orders are only recorded for approved requests.
     */
    public function canReceiveQuotations(): bool
    {
        return $this->status === 'approved';
    }

    public function canBeOrdered(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Requests can be cancelled until goods start arriving.
     */
    public function isCancellable(): bool
    {
        return ! in_array($this->status, ['partially_received', 'received', 'cancelled'], true);
    }

    /**
     * Update the request status from the quantities received so far.
     */
    public function syncReceiptStatus(): void
    {
        $items = $this->items()->get();
        $received = $items->sum(fn (ProcurementRequestItem $i) => (float) $i->quantity_received);

        if ($received <= 0) {
            return;
        }

        $complete = $items->every(fn (ProcurementRequestItem $i) => $i->outstandingQuantity() <= 0);
        $this->forceFill(['status' => $complete ? 'received' : 'partially_received'])->save();
    }
}
