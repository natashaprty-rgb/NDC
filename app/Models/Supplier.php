<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'type', 'category_id', 'registration_number', 'tax_number', 'contact_person', 'email', 'phone',
    'address', 'website', 'status', 'notes', 'created_by',
])]
class Supplier extends Model
{
    use Auditable, HasAttachments, HasFactory, SoftDeletes;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(SupplierEvaluation::class)->latest();
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Average of all evaluation scores (1-5), or null when not yet evaluated.
     */
    public function averageRating(): ?float
    {
        $avg = $this->evaluations()->reorder()
            ->selectRaw('AVG((quality_rating + delivery_rating + communication_rating) / 3.0) as score')
            ->value('score');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    /**
     * @return array<int, string>
     */
    public static function options(): array
    {
        return static::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Overall rating using per-criterion averages loaded with withAvg()
     * (quality_avg, delivery_avg, communication_avg) when present, so list
     * pages avoid one query per row.
     */
    public function overallRating(): ?float
    {
        if (array_key_exists('quality_avg', $this->attributes)) {
            if ($this->attributes['quality_avg'] === null) {
                return null;
            }

            return round(((float) $this->quality_avg + (float) $this->delivery_avg + (float) $this->communication_avg) / 3, 1);
        }

        return $this->averageRating();
    }
}
