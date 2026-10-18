<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    use Auditable, HasAttachments, HasUserstamps;

    protected $fillable = ['purchase_request_id', 'supplier_id', 'supplier_reference', 'quotation_date', 'valid_until', 'total_amount', 'is_selected', 'notes'];

    protected function casts(): array
    {
        return ['quotation_date' => 'date', 'valid_until' => 'date', 'is_selected' => 'boolean'];
    }

    public function purchaseRequest(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }
}
