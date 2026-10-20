<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['procurement_request_item_id', 'quantity'])]
class GoodsReceiptItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequestItem::class, 'procurement_request_item_id');
    }
}
