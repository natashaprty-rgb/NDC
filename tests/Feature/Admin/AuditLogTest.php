<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\LoginActivity;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    public function test_auditor_can_view_and_filter_audit_trail(): void
    {
        $actor = $this->userWithRole('system-admin');
        $this->actingAs($actor);
        $dept = Department::query()->create(['code' => 'AUD1', 'name' => 'Audit Target Dept']);
        $dept->update(['name' => 'Renamed Dept']);
        AuditLog::query()->create(['event' => 'exported', 'description' => 'Unrelated export entry']);

        $auditor = $this->userWithRole('auditor');
        $this->actingAs($auditor)->get('/admin/audit?type=department&event=updated')->assertOk()
            ->assertSee('Renamed Dept')->assertDontSee('Unrelated export entry');
        $this->actingAs($auditor)->get('/admin/audit?q=Unrelated')->assertSee('Unrelated export entry');
        $this->actingAs($auditor)->get('/admin/audit?user='.$actor->id.'&event=exported')->assertDontSee('Unrelated export entry');
    }

    public function test_login_activity_is_listed_and_filterable(): void
    {
        LoginActivity::query()->create(['email' => 'victim@example.test', 'event' => 'failed', 'ip_address' => '10.0.0.9']);
        LoginActivity::query()->create(['email' => 'other@example.test', 'event' => 'login', 'ip_address' => '10.0.0.1']);
        $auditor = $this->userWithRole('auditor');

        $this->actingAs($auditor)->get('/admin/audit/logins?event=failed')->assertOk()
            ->assertSee('victim@example.test')->assertDontSee('other@example.test');
    }

    public function test_auditor_cannot_modify_anything_in_admin_routes(): void
    {
        $auditor = $this->userWithRole('auditor');
        $target = $this->userWithRole('staff');
        $role = Role::query()->where('slug', 'staff')->first();

        $this->actingAs($auditor)->post('/admin/users', ['name' => 'x', 'email' => 'x@example.test', 'locale' => 'en', 'password_mode' => 'generate'])->assertForbidden();
        $this->actingAs($auditor)->post("/admin/users/{$target->id}/activate")->assertForbidden();
        $this->actingAs($auditor)->post('/admin/roles', ['name' => 'x'])->assertForbidden();
        $this->actingAs($auditor)->put("/admin/roles/{$role->id}", ['name' => 'x'])->assertForbidden();
        $this->actingAs($auditor)->delete("/admin/roles/{$role->id}")->assertForbidden();
        $this->actingAs($auditor)->post('/admin/permission-reviews', [])->assertForbidden();
        $this->actingAs($auditor)->post('/staff', ['staff_number' => 'z'])->assertForbidden();
        $this->assertFalse(User::query()->where('email', 'x@example.test')->exists());
    }

    public function test_admin_pages_render_for_super_admin_in_both_languages(): void
    {
        $super = $this->userWithRole('super-admin');
        $other = $this->userWithRole('staff');
        $role = Role::query()->where('slug', 'director')->first();
        $member = \App\Models\Staff::factory()->create();
        AuditLog::query()->create(['event' => 'updated', 'auditable_type' => 'user', 'auditable_id' => $other->id, 'old_values' => ['name' => 'A'], 'new_values' => ['name' => 'B']]);

        foreach (['en', 'so'] as $locale) {
            $super->update(['locale' => $locale]);
            foreach (['/admin/users/create', "/admin/users/{$other->id}/edit", "/admin/users/{$other->id}", '/admin/roles', "/admin/roles/{$role->id}",
                "/admin/roles/{$role->id}/edit", '/admin/roles/create', '/admin/audit', '/admin/audit/logins', '/admin/permission-reviews/create',
                '/staff', '/staff/create', "/staff/{$member->id}", "/staff/{$member->id}/edit"] as $url) {
                $this->actingAs($super->fresh())->get($url)->assertOk();
            }
        }
    }

    public function test_audit_logs_require_audit_permission(): void
    {
        $this->actingAs($this->userWithRole('staff'))->get('/admin/audit')->assertForbidden();
        $this->actingAs($this->userWithRole('project-manager'))->get('/admin/audit/logins')->assertForbidden();
    }
}
