<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['received_on', 'received_by', 'delivery_note_number', 'notes'])]
class GoodsReceipt extends Model
{
    use Auditable, HasAttachments;

    protected function casts(): array
    {
        return ['received_on' => 'date'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /**
     * Attachments on this record are authorised through its parent.
     */
    public function attachmentAuthorizationTarget(): Model
    {
        return $this->purchaseOrder->request;
    }
}
