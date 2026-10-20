<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplier_id', 'reference', 'amount', 'currency', 'received_on', 'valid_until', 'is_selected', 'notes'])]
class Quotation extends Model
{
    use Auditable, HasAttachments, HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'received_on' => 'date',
            'valid_until' => 'date',
            'is_selected' => 'boolean',
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

    /**
     * Attachments on this record are authorised through its parent.
     */
    public function attachmentAuthorizationTarget(): Model
    {
        return $this->request;
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->lt(today());
    }
}
