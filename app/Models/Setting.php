<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Auditable;

    protected $fillable = ['key', 'value', 'type', 'updated_by'];

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'bool' => $this->value === '1',
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
