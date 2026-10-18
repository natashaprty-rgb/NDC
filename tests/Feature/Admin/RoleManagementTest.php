<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    public function test_custom_role_created_and_permissions_take_effect(): void
    {
        $super = $this->userWithRole('super-admin');

        $this->actingAs($super)->post('/admin/roles', [
            'name' => 'Registry Clerk', 'description' => 'Correspondence only',
            'permissions' => ['dashboard.view', 'correspondence.view'],
        ])->assertRedirect();

        $role = Role::query()->where('slug', 'registry-clerk')->firstOrFail();
        $this->assertFalse($role->is_system);

        $clerk = User::factory()->create();
        $clerk->roles()->attach($role->id);
        $this->assertTrue($clerk->fresh()->can('correspondence.view'));
        $this->assertFalse($clerk->fresh()->can('correspondence.manage'));

        $this->actingAs($super)->put("/admin/roles/{$role->id}", [
            'name' => 'Registry Clerk', 'permissions' => ['dashboard.view', 'correspondence.view', 'correspondence.manage'],
        ])->assertRedirect(route('admin.roles.show', $role));

        $this->assertTrue($clerk->fresh()->can('correspondence.manage'));
        $log = AuditLog::query()->where('auditable_type', 'role')->where('auditable_id', $role->id)->where('description', 'Permissions changed')->latest('id')->first();
        $this->assertContains('correspondence.manage', $log->new_values['permissions']);
        $this->assertNotContains('correspondence.manage', $log->old_values['permissions']);
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $super = $this->userWithRole('super-admin');
        $this->actingAs($super)->post('/admin/roles', ['name' => 'Bad', 'permissions' => ['god.mode']])->assertSessionHasErrors('permissions.0');
    }

    public function test_super_admin_role_is_read_only(): void
    {
        $super = $this->userWithRole('super-admin');
        $role = Role::query()->where('slug', 'super-admin')->first();

        $this->actingAs($super)->get("/admin/roles/{$role->id}")->assertOk();
        $this->actingAs($super)->get("/admin/roles/{$role->id}/edit")->assertForbidden();
        $this->actingAs($super)->put("/admin/roles/{$role->id}", ['name' => 'X', 'permissions' => []])->assertForbidden();
        $this->actingAs($super)->delete("/admin/roles/{$role->id}")->assertForbidden();
    }

    public function test_system_roles_and_roles_in_use_cannot_be_deleted(): void
    {
        $super = $this->userWithRole('super-admin');
        $system = Role::query()->where('slug', 'staff')->first();
        $this->actingAs($super)->delete("/admin/roles/{$system->id}")->assertForbidden();

        $custom = Role::query()->create(['slug' => 'temp', 'name' => 'Temp']);
        $holder = User::factory()->create();
        $holder->roles()->attach($custom->id);
        $this->actingAs($super)->delete("/admin/roles/{$custom->id}")->assertForbidden();

        $holder->roles()->detach($custom->id);
        $this->actingAs($super)->delete("/admin/roles/{$custom->id}")->assertRedirect(route('admin.roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $custom->id]);
    }

    public function test_users_without_roles_manage_cannot_change_roles(): void
    {
        $sysadmin = $this->userWithRole('system-admin'); // users.view but not roles.manage
        $role = Role::query()->where('slug', 'staff')->first();

        $this->actingAs($sysadmin)->get('/admin/roles')->assertOk();
        $this->actingAs($sysadmin)->get('/admin/roles/create')->assertForbidden();
        $this->actingAs($sysadmin)->put("/admin/roles/{$role->id}", ['name' => 'General Staff', 'permissions' => ['users.manage']])->assertForbidden();
        $this->assertFalse($role->fresh()->permissions->contains('slug', 'users.manage'));

        $this->actingAs($this->userWithRole('staff'))->get('/admin/roles')->assertForbidden();
    }
}
