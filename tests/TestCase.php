<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Permission;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed permissions and default roles before each test that uses
     * RefreshDatabase.
     */
    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    /**
     * Create an active user holding one of the default roles.
     */
    protected function userWithRole(string $role, array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    /**
     * Create an active user with exactly the given permissions (no role).
     *
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(array $permissions, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        $user->givePermissionTo($permissions);

        return $user;
    }

    protected function superAdmin(array $attributes = []): User
    {
        return $this->userWithRole(config('ndc.super_admin_role'), $attributes);
    }
}
