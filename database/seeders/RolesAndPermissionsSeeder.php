<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates every permission in config('ndc.permissions') and the default
 * roles. Safe to re-run: existing roles keep any permissions added through
 * the interface unless --fresh-roles style syncing is requested.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function __construct(private bool $syncRoles = false) {}

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $groups = config('ndc.permissions');
        foreach ($groups as $permissions) {
            foreach (array_keys($permissions) as $name) {
                Permission::findOrCreate($name, 'web');
            }
        }

        foreach (config('ndc.roles') as $roleName => $grants) {
            $role = Role::findOrCreate($roleName, 'web');

            if (! $role->wasRecentlyCreated && ! $this->syncRoles) {
                continue;
            }

            $role->syncPermissions($this->expand($grants, $groups));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Expand "module.*" wildcards into concrete permission names.
     *
     * @param  list<string>  $grants
     * @param  array<string, array<string, string>>  $groups
     * @return list<string>
     */
    public static function expand(array $grants, array $groups): array
    {
        $names = [];
        foreach ($grants as $grant) {
            if (str_ends_with($grant, '.*')) {
                $module = substr($grant, 0, -2);
                $names = array_merge($names, array_keys($groups[$module] ?? []));
            } else {
                $names[] = $grant;
            }
        }

        return array_values(array_unique($names));
    }
}
