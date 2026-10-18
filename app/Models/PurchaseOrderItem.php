<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    protected $fillable = ['purchase_order_id', 'purchase_request_item_id', 'description', 'quantity', 'unit', 'unit_price', 'line_total', 'quantity_received'];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class)->withTrashed();
    }

    /** Outstanding quantity in hundredths (integer) to avoid float comparisons. */
    public function outstandingHundredths(): int
    {
        return \App\Support\Money::toCents((string) $this->quantity) - \App\Support\Money::toCents((string) $this->quantity_received);
    }
}
