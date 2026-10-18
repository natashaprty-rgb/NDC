<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends Model
{
    use Auditable, HasUserstamps;

    protected $fillable = ['code', 'name', 'name_so', 'description', 'retention_years', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'retention_years' => 'integer'];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'category_id');
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'so' && $this->name_so ? $this->name_so : $this->name;
    }
}
