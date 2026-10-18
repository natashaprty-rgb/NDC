<?php

namespace Tests\Feature\Foundation;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_every_role_in_the_matrix_references_existing_permissions(): void
    {
        $catalog = Permission::allSlugs();
        foreach (config('ndc_permissions.roles') as $slug => $role) {
            foreach ($role['permissions'] as $permission) {
                if ($permission !== '*') {
                    $this->assertContains($permission, $catalog, "Role {$slug} references unknown permission {$permission}");
                }
            }
        }
        $this->assertSame(count($catalog), Permission::query()->count());
    }

    public function test_permissions_are_granted_through_roles(): void
    {
        $officer = $this->userWithRole('finance-officer');

        $this->assertTrue($officer->can('expenses.record'));
        $this->assertFalse($officer->can('expenses.approve'));
        $this->assertFalse($officer->can('users.manage'));
    }

    public function test_super_admin_holds_every_permission(): void
    {
        $admin = $this->userWithRole('super-admin');
        foreach (Permission::allSlugs() as $slug) {
            $this->assertTrue($admin->can($slug), $slug);
        }
    }

    public function test_auditor_role_has_no_write_permissions(): void
    {
        $auditor = $this->userWithRole('auditor');
        $writes = ['users.manage', 'roles.manage', 'settings.manage', 'projects.create', 'projects.manage', 'tasks.create',
            'documents.create', 'documents.manage', 'correspondence.manage', 'meetings.manage', 'assets.manage', 'suppliers.manage',
            'procurement.request', 'procurement.approve', 'budgets.manage', 'expenses.record', 'expenses.approve', 'staff.manage'];
        foreach ($writes as $slug) {
            $this->assertFalse($auditor->can($slug), "Auditor must not hold {$slug}");
        }
    }

    public function test_suspended_user_has_no_permissions(): void
    {
        $user = $this->userWithRole('director', ['status' => 'suspended']);
        $this->assertFalse($user->can('dashboard.view'));
    }

    public function test_route_permission_middleware_returns_403(): void
    {
        $staff = $this->userWithRole('staff');
        $userWithoutDashboard = $this->userWithPermissions([]);

        $this->actingAs($staff)->get('/dashboard')->assertOk();
        $this->actingAs($userWithoutDashboard)->get('/dashboard')->assertForbidden();
    }

    public function test_unknown_abilities_are_not_granted_by_the_permission_gate(): void
    {
        $admin = $this->userWithRole('super-admin');
        $this->assertFalse(Gate::forUser($admin)->allows('some.undefined.ability'));
    }

    public function test_only_super_admin_can_modify_super_admin_accounts(): void
    {
        $super = $this->userWithRole('super-admin');
        $sysadmin = $this->userWithRole('system-admin');

        $this->assertFalse($sysadmin->can('update', $super));
        $this->assertFalse($sysadmin->can('grantSuperAdmin', \App\Models\User::class));
        $this->assertTrue($super->can('update', $sysadmin));
        $this->assertFalse($super->can('suspend', $super), 'Nobody may suspend themselves');
    }

    public function test_super_admin_role_cannot_be_edited_or_deleted(): void
    {
        $super = $this->userWithRole('super-admin');
        $role = Role::query()->where('slug', 'super-admin')->first();
        $this->assertFalse($super->can('update', $role));
        $this->assertFalse($super->can('delete', $role));
    }
}
