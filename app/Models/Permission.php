<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['slug', 'group', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /** All permission slugs defined in config/ndc_permissions.php. */
    public static function allSlugs(): array
    {
        return collect(config('ndc_permissions.permissions'))->flatMap(fn ($group) => array_keys($group))->all();
    }
}
