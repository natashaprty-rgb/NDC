<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetCategory extends Model
{
    use Auditable, HasUserstamps;

    /** "data_centre" is reserved for future server/rack/network/storage equipment. */
    public const CLASSES = ['office', 'ict', 'furniture', 'vehicle', 'data_centre', 'other'];

    protected $fillable = ['code', 'name', 'name_so', 'asset_class', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'category_id');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'so' && $this->name_so ? $this->name_so : $this->name;
    }
}
