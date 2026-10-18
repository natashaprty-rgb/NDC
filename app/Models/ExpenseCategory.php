<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use Auditable, HasUserstamps;

    protected $fillable = ['code', 'name', 'name_so', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'so' && $this->name_so ? $this->name_so : $this->name;
    }
}
