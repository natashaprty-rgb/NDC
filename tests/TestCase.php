<?php

namespace Tests;

use App\Models\Department;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = RolesAndPermissionsSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Settings::flush();
    }

    /** Create an active user holding one of the seeded roles (by slug). */
    protected function userWithRole(string $roleSlug, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::query()->where('slug', $roleSlug)->firstOrFail()->id);

        return $user->fresh();
    }

    /** Create an active user with exactly the given permissions (through an ad-hoc role). */
    protected function userWithPermissions(array $permissions, array $attributes = []): User
    {
        $role = Role::query()->create(['slug' => 'test-'.Str::random(8), 'name' => 'Test role']);
        $role->permissions()->sync(\App\Models\Permission::query()->whereIn('slug', $permissions)->pluck('id'));
        $user = User::factory()->create($attributes);
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    /** Link a user to a staff record in a department (department membership drives some visibility rules). */
    protected function placeInDepartment(User $user, ?Department $department = null): Department
    {
        $department ??= Department::factory()->create();
        Staff::factory()->create(['user_id' => $user->id, 'department_id' => $department->id, 'full_name' => $user->name]);
        $user->unsetRelation('staff');

        return $department;
    }
}
