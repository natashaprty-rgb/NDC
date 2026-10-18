<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMaintenance extends Model
{
    use Auditable, HasUserstamps;

    public const TYPES = ['preventive', 'corrective', 'inspection'];

    public const STATUSES = ['scheduled', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = ['asset_id', 'type', 'description', 'provider', 'supplier_id', 'scheduled_date', 'completed_date', 'cost', 'status', 'notes'];

    protected function casts(): array
    {
        return ['scheduled_date' => 'date', 'completed_date' => 'date'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class)->withTrashed();
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }
}
