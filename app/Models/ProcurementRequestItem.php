<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['description', 'specifications', 'quantity', 'unit', 'estimated_unit_cost', 'quantity_received'])]
class ProcurementRequestItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'estimated_unit_cost' => 'decimal:2',
            'quantity_received' => 'decimal:2',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ProcurementRequest::class, 'procurement_request_id');
    }

    public function lineTotal(): float
    {
        return (float) $this->quantity * (float) $this->estimated_unit_cost;
    }

    public function outstandingQuantity(): float
    {
        return max(0, (float) $this->quantity - (float) $this->quantity_received);
    }
}
