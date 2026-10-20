<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Configurable reference data (document types, asset categories, expense
 * categories, supplier categories, funding sources).
 */
#[Fillable(['type', 'name', 'name_so', 'code', 'description', 'sort_order', 'is_active'])]
class Category extends Model
{
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'so' && $this->name_so ? $this->name_so : $this->name;
    }

    /**
     * Active categories of a type as id => name pairs. Pass $includeId so an
     * edit form still shows a category that has since been deactivated.
     *
     * @return array<int, string>
     */
    public static function options(string $type, ?int $includeId = null): array
    {
        return static::query()
            ->ofType($type)
            ->where(fn (Builder $q) => $q->where('is_active', true)->when($includeId, fn ($q) => $q->orWhere('id', $includeId)))
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Category $c) => [$c->id => $c->displayName()])
            ->all();
    }
}
