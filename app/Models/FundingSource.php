<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasUserstamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FundingSource extends Model
{
    use Auditable, HasFactory, HasUserstamps;

    public const TYPES = ['government', 'donor', 'grant', 'loan', 'other'];

    protected $fillable = ['code', 'name', 'type', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }
}
