<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use Auditable, HasAttachments, HasFactory, HasUserstamps, SoftDeletes;

    public const STATUSES = ['active', 'inactive', 'blacklisted'];

    protected $fillable = [
        'code', 'name', 'registration_number', 'tax_number', 'contact_person', 'email', 'phone',
        'address', 'country', 'services', 'status', 'notes',
    ];

    public function contracts(): HasMany
    {
        return $this->hasMany(SupplierContract::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
