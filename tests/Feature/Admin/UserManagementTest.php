<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    private function roleId(string $slug): int
    {
        return Role::query()->where('slug', $slug)->value('id');
    }

    public function test_admin_creates_user_with_generated_password_and_roles(): void
    {
        $admin = $this->userWithRole('system-admin');

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'New Officer', 'email' => 'NEW@example.test', 'locale' => 'so',
            'password_mode' => 'generate', 'roles' => [$this->roleId('project-officer')],
        ]);

        $user = User::query()->where('email', 'new@example.test')->firstOrFail();
        $response->assertRedirect(route('admin.users.show', $user))->assertSessionHas('temporary_password');
        $this->assertTrue(Hash::check(session('temporary_password'), $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->hasRole('project-officer'));
        $this->assertSame($admin->id, (int) $user->roles->first()->pivot->assigned_by);
        $this->assertTrue(AuditLog::query()->where('event', 'role_changed')->where('auditable_id', $user->id)->exists());
    }

    public function test_manual_password_must_meet_policy_and_email_unique(): void
    {
        $admin = $this->userWithRole('system-admin');
        $this->userWithRole('staff', ['email' => 'taken@example.test']);

        $this->actingAs($admin)->post('/admin/users', ['name' => 'X', 'email' => 'x@example.test', 'locale' => 'en', 'password_mode' => 'manual', 'password' => 'weak'])
            ->assertSessionHasErrors('password');
        $this->actingAs($admin)->post('/admin/users', ['name' => 'X', 'email' => 'taken@example.test', 'locale' => 'en', 'password_mode' => 'generate'])
            ->assertSessionHasErrors('email');
    }

    public function test_system_admin_cannot_grant_super_admin(): void
    {
        $sysadmin = $this->userWithRole('system-admin');
        $target = $this->userWithRole('staff');

        $this->actingAs($sysadmin)->put("/admin/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email, 'locale' => 'en',
            'roles' => [$this->roleId('staff'), $this->roleId('super-admin')],
        ])->assertSessionHasErrors('roles');
        $this->assertFalse($target->fresh()->isSuperAdmin());

        $this->actingAs($sysadmin)->post('/admin/users', [
            'name' => 'Sneaky', 'email' => 'sneaky@example.test', 'locale' => 'en', 'password_mode' => 'generate',
            'roles' => [$this->roleId('super-admin')],
        ])->assertSessionHasErrors('roles');
        $this->assertFalse(User::query()->where('email', 'sneaky@example.test')->exists());
    }

    public function test_super_admin_can_grant_super_admin_and_change_roles(): void
    {
        $super = $this->userWithRole('super-admin');
        $target = $this->userWithRole('staff');

        $this->actingAs($super)->put("/admin/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email, 'locale' => 'en',
            'roles' => [$this->roleId('super-admin')],
        ])->assertRedirect(route('admin.users.show', $target));

        $fresh = $target->fresh();
        $this->assertTrue($fresh->isSuperAdmin());
        $this->assertFalse($fresh->hasRole('staff'));
    }

    public function test_system_admin_cannot_modify_a_super_admin_account(): void
    {
        $sysadmin = $this->userWithRole('system-admin');
        $super = $this->userWithRole('super-admin');

        $this->actingAs($sysadmin)->get("/admin/users/{$super->id}/edit")->assertForbidden();
        $this->actingAs($sysadmin)->post("/admin/users/{$super->id}/suspend", ['reason' => 'x'])->assertForbidden();
    }

    public function test_cannot_change_own_roles_or_suspend_self(): void
    {
        $admin = $this->userWithRole('super-admin');

        $this->actingAs($admin)->put("/admin/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'locale' => 'en', 'roles' => [$this->roleId('staff')],
        ])->assertSessionHasErrors('roles');
        $this->assertTrue($admin->fresh()->isSuperAdmin());

        $this->actingAs($admin)->post("/admin/users/{$admin->id}/suspend", ['reason' => 'test'])->assertForbidden();
        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")->assertForbidden();
    }

    public function test_suspend_logs_out_user_and_activate_restores_access(): void
    {
        $admin = $this->userWithRole('system-admin');
        $target = $this->userWithRole('staff');

        $this->actingAs($admin)->post("/admin/users/{$target->id}/suspend", [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post("/admin/users/{$target->id}/suspend", ['reason' => 'Left the organisation'])->assertRedirect();

        $fresh = $target->fresh();
        $this->assertSame('suspended', $fresh->status);
        $this->assertSame('Left the organisation', $fresh->suspension_reason);
        $this->assertTrue(AuditLog::query()->where('event', 'account_suspended')->where('auditable_id', $target->id)->exists());

        $this->actingAs($fresh)->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($admin)->post("/admin/users/{$target->id}/activate")->assertRedirect();
        $this->actingAs($target->fresh())->get('/dashboard')->assertOk();
    }

    public function test_admin_password_reset_issues_temporary_password(): void
    {
        $admin = $this->userWithRole('system-admin');
        $target = $this->userWithRole('staff', ['password' => 'Original-Pass-1!']);

        $this->actingAs($admin)->post("/admin/users/{$target->id}/reset-password")->assertSessionHas('temporary_password');
        $fresh = $target->fresh();
        $this->assertTrue($fresh->must_change_password);
        $this->assertFalse(Hash::check('Original-Pass-1!', $fresh->password));
        $this->assertTrue(Hash::check(session('temporary_password'), $fresh->password));
        $this->assertTrue(AuditLog::query()->where('event', 'password_reset')->where('auditable_id', $target->id)->exists());
    }

    public function test_archive_soft_deletes_user(): void
    {
        $admin = $this->userWithRole('system-admin');
        $target = $this->userWithRole('staff');

        $this->actingAs($admin)->delete("/admin/users/{$target->id}")->assertRedirect(route('admin.users.index'));
        $this->assertSoftDeleted($target);
    }

    public function test_index_filters_and_hides_secrets(): void
    {
        $admin = $this->userWithRole('system-admin');
        $this->userWithRole('auditor', ['name' => 'Audit Person']);
        $this->userWithRole('staff', ['name' => 'Staff Person', 'status' => 'suspended']);

        $this->actingAs($admin)->get('/admin/users?role='.$this->roleId('auditor'))->assertSee('Audit Person')->assertDontSee('Staff Person');
        $this->actingAs($admin)->get('/admin/users?status=suspended')->assertSee('Staff Person')->assertDontSee('Audit Person');

        $target = User::query()->where('name', 'Audit Person')->first();
        $this->actingAs($admin)->get("/admin/users/{$target->id}")->assertOk()->assertDontSee($target->password);
    }

    public function test_non_admins_are_forbidden(): void
    {
        $staff = $this->userWithRole('staff');
        $other = $this->userWithRole('staff');

        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
        $this->actingAs($staff)->get("/admin/users/{$other->id}")->assertForbidden();
        $this->actingAs($staff)->post('/admin/users', ['name' => 'x'])->assertForbidden();

        // Auditor can view but not modify.
        $auditor = $this->userWithRole('auditor');
        $this->actingAs($auditor)->get('/admin/users')->assertOk();
        $this->actingAs($auditor)->get('/admin/users/create')->assertForbidden();
        $this->actingAs($auditor)->post("/admin/users/{$other->id}/suspend", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($auditor)->post("/admin/users/{$other->id}/reset-password")->assertForbidden();
        $this->actingAs($auditor)->put("/admin/users/{$other->id}", ['name' => 'x', 'email' => 'x@example.test', 'locale' => 'en'])->assertForbidden();
        $this->actingAs($auditor)->delete("/admin/users/{$other->id}")->assertForbidden();
    }
}
