<?php

namespace Tests\Feature\Core;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render_for_super_admin(): void
    {
        $admin = $this->superAdmin();
        Department::query()->create(['name' => 'ICT', 'code' => 'ICT']);

        foreach ([
            'admin.users.index', 'admin.users.create', 'admin.roles.index', 'admin.roles.create',
            'admin.departments.index', 'admin.departments.create', 'admin.categories.index',
            'admin.settings.edit', 'admin.audit.index', 'admin.system.index', 'admin.backups.index',
            'approvals.index', 'notifications.index', 'profile.edit', 'profile.security', 'dashboard',
        ] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }

        $this->actingAs($admin)->get(route('admin.users.show', $admin))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.edit', $admin))->assertOk();
        $this->actingAs($admin)->get(route('admin.roles.edit', Role::findByName('Project Manager')))->assertOk();
    }

    public function test_general_staff_cannot_reach_administration(): void
    {
        $staff = $this->userWithRole('General Staff');

        foreach (['admin.users.index', 'admin.roles.index', 'admin.settings.edit', 'admin.audit.index', 'admin.system.index'] as $route) {
            $this->actingAs($staff)->get(route($route))->assertForbidden();
        }
    }

    public function test_auditor_can_read_audit_log_but_not_manage_users(): void
    {
        $auditor = $this->userWithRole('Read-Only Auditor');

        $this->actingAs($auditor)->get(route('admin.audit.index'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_admin_can_create_user_with_roles(): void
    {
        $admin = $this->userWithRole('System Administrator');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Amina Ali', 'email' => 'amina@ndc.test', 'status' => 'active', 'locale' => 'so',
            'password' => 'Str0ng#Passw0rd', 'password_confirmation' => 'Str0ng#Passw0rd',
            'roles' => ['Project Officer'],
        ])->assertRedirect();

        $user = User::query()->where('email', 'amina@ndc.test')->firstOrFail();
        $this->assertTrue($user->hasRole('Project Officer'));
        $this->assertTrue($user->must_change_password);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'user', 'auditable_id' => $user->id]);
    }

    public function test_non_super_admin_cannot_grant_super_admin_role(): void
    {
        $admin = $this->userWithRole('System Administrator');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Escalation', 'email' => 'esc@ndc.test', 'status' => 'active', 'locale' => 'en',
            'password' => 'Str0ng#Passw0rd', 'password_confirmation' => 'Str0ng#Passw0rd',
            'roles' => [config('ndc.super_admin_role')],
        ]);

        $this->assertFalse(User::query()->where('email', 'esc@ndc.test')->firstOrFail()->isSuperAdmin());
    }

    public function test_admin_cannot_suspend_themselves(): void
    {
        $admin = $this->userWithRole('System Administrator');

        $this->actingAs($admin)->patch(route('admin.users.status', $admin), ['status' => 'suspended'])->assertStatus(422);
        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_suspending_a_user_revokes_api_tokens(): void
    {
        $admin = $this->userWithRole('System Administrator');
        $user = $this->userWithRole('General Staff');
        $user->createToken('integration');

        $this->actingAs($admin)->patch(route('admin.users.status', $user), ['status' => 'suspended'])->assertRedirect();

        $this->assertSame('suspended', $user->fresh()->status);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_role_permissions_can_be_edited_and_are_audited(): void
    {
        $admin = $this->userWithRole('System Administrator');
        $role = Role::findByName('General Staff');

        $this->actingAs($admin)->put(route('admin.roles.update', $role), [
            'name' => 'General Staff', 'permissions' => ['dashboard.view', 'projects.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertTrue($role->fresh()->hasPermissionTo('projects.view'));
        $this->assertFalse($role->fresh()->hasPermissionTo('tasks.view'));
        $this->assertTrue(AuditLog::query()->where('event', 'role_updated')->exists());
    }

    public function test_super_admin_role_cannot_be_edited(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('admin.roles.edit', Role::findByName(config('ndc.super_admin_role'))))->assertForbidden();
    }

    public function test_department_crud_and_delete_protection(): void
    {
        $admin = $this->userWithRole('System Administrator');

        $this->actingAs($admin)->post(route('admin.departments.store'), [
            'name' => 'Finance', 'name_so' => 'Maaliyadda', 'code' => 'FIN', 'is_active' => 1,
        ])->assertRedirect();
        $department = Department::query()->where('code', 'FIN')->firstOrFail();

        User::factory()->create(['department_id' => $department->id]);
        $this->actingAs($admin)->delete(route('admin.departments.destroy', $department))->assertSessionHas('error');
        $this->assertNotSoftDeleted($department);
    }

    public function test_settings_reject_executable_upload_types(): void
    {
        $admin = $this->userWithRole('System Administrator');
        $payload = array_merge(config('ndc.settings'), [
            'upload_allowed_extensions' => 'pdf, php, docx, exe',
            'mail_notifications_enabled' => 0, 'require_two_factor_for_admins' => 0,
        ]);

        $this->actingAs($admin)->put(route('admin.settings.update'), $payload)->assertSessionHasNoErrors();

        $this->assertSame('pdf,docx', setting('upload_allowed_extensions'));
        $this->assertTrue(AuditLog::query()->where('event', 'settings_updated')->exists());
    }

    public function test_reference_data_can_be_added(): void
    {
        $admin = $this->userWithRole('System Administrator');

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'type' => 'asset', 'name' => 'Biometric Reader', 'name_so' => 'Akhriste', 'is_active' => 1,
        ])->assertRedirect();

        $this->assertTrue(Category::query()->ofType('asset')->where('name', 'Biometric Reader')->exists());
    }

    public function test_audit_log_never_stores_passwords(): void
    {
        $user = User::factory()->create();
        $user->update(['password' => 'An0ther#Secret']);

        $leaked = AuditLog::query()->get()->contains(fn (AuditLog $log) => array_key_exists('password', $log->new_values ?? [])
            || array_key_exists('password', $log->old_values ?? []));
        $this->assertFalse($leaked);
    }
}
