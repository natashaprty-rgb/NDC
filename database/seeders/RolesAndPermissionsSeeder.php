<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Idempotent: adds missing permissions and roles from config/ndc_permissions.php.
 * Existing role permissions are only extended, never reduced, so changes an
 * administrator made in the UI are preserved when the seeder is re-run.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('ndc_permissions.permissions') as $group => $permissions) {
            foreach ($permissions as $slug => $description) {
                Permission::query()->updateOrCreate(['slug' => $slug], ['group' => $group, 'description' => $description]);
            }
        }

        $allIds = Permission::query()->pluck('id', 'slug');

        foreach (config('ndc_permissions.roles') as $slug => $definition) {
            $role = Role::query()->firstOrCreate(['slug' => $slug], [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'is_system' => true,
            ]);

            $ids = $definition['permissions'] === ['*']
                ? $allIds->values()
                : $allIds->only($definition['permissions'])->values();

            $role->permissions()->syncWithoutDetaching($ids->all());
        }
    }
}
