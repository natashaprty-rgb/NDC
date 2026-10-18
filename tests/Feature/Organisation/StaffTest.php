<?php

namespace Tests\Feature\Organisation;

use App\Models\Department;
use App\Models\Staff;
use App\Models\Task;
use Tests\TestCase;

class StaffTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'staff_number' => 'S-0001',
            'full_name' => 'Test Officer One',
            'job_title' => 'Records Officer',
            'official_email' => 'records@example.test',
            'employment_status' => 'active',
            'employment_type' => 'contract',
            'start_date' => '2025-01-01',
            'notes' => 'Probation review pending',
        ], $overrides);
    }

    public function test_manager_can_register_update_and_archive_staff(): void
    {
        $admin = $this->userWithRole('system-admin');
        $dept = Department::factory()->create();

        $this->actingAs($admin)->post('/staff', $this->payload(['department_id' => $dept->id]))->assertRedirect();
        $member = Staff::query()->where('staff_number', 'S-0001')->firstOrFail();
        $this->assertSame($dept->id, $member->department_id);
        $this->assertSame($admin->id, $member->created_by);

        $this->actingAs($admin)->put("/staff/{$member->id}", $this->payload(['job_title' => 'Senior Records Officer']))
            ->assertRedirect(route('staff.show', $member));
        $this->assertSame('Senior Records Officer', $member->fresh()->job_title);

        $this->actingAs($admin)->delete("/staff/{$member->id}")->assertRedirect(route('staff.index'));
        $this->assertSoftDeleted($member);
    }

    public function test_validation_rules_are_enforced(): void
    {
        $admin = $this->userWithRole('system-admin');
        Staff::factory()->create(['staff_number' => 'S-0001']);
        $linked = $this->userWithRole('staff');
        Staff::factory()->create(['user_id' => $linked->id]);

        $this->actingAs($admin)->post('/staff', $this->payload())->assertSessionHasErrors('staff_number');
        $this->actingAs($admin)->post('/staff', $this->payload(['staff_number' => 'S-2', 'employment_status' => 'fired']))->assertSessionHasErrors('employment_status');
        $this->actingAs($admin)->post('/staff', $this->payload(['staff_number' => 'S-3', 'start_date' => '2025-05-01', 'end_date' => '2025-01-01']))->assertSessionHasErrors('end_date');
        $this->actingAs($admin)->post('/staff', $this->payload(['staff_number' => 'S-4', 'user_id' => $linked->id]))->assertSessionHasErrors('user_id');
    }

    public function test_sensitive_fields_hidden_from_general_staff_but_visible_to_department_head(): void
    {
        $member = Staff::factory()->create(['notes' => 'Confidential HR note', 'employment_type' => 'secondment']);
        $staff = $this->userWithRole('staff');
        $head = $this->userWithRole('department-head');

        $this->actingAs($staff)->get("/staff/{$member->id}")->assertOk()
            ->assertSee($member->full_name)->assertDontSee('Confidential HR note')->assertDontSee(__('staff.employment_details'));
        $this->actingAs($head)->get("/staff/{$member->id}")->assertOk()
            ->assertSee('Confidential HR note')->assertSee(__('staff.employment_details'));
    }

    public function test_status_filter_only_applies_for_sensitive_viewers(): void
    {
        Staff::factory()->create(['full_name' => 'Active Person', 'employment_status' => 'active']);
        Staff::factory()->create(['full_name' => 'Leave Person', 'employment_status' => 'on_leave']);

        $this->actingAs($this->userWithRole('department-head'))->get('/staff?status=on_leave')
            ->assertSee('Leave Person')->assertDontSee('Active Person');
        // General staff cannot probe employment status through the filter.
        $this->actingAs($this->userWithRole('staff'))->get('/staff?status=on_leave')
            ->assertSee('Leave Person')->assertSee('Active Person');
    }

    public function test_search_finds_by_name_number_and_title(): void
    {
        Staff::factory()->create(['full_name' => 'Amina Example', 'staff_number' => 'X-77', 'job_title' => 'Analyst']);
        Staff::factory()->create(['full_name' => 'Other Person', 'job_title' => 'Driver']);
        $user = $this->userWithRole('staff');

        $this->actingAs($user)->get('/staff?q=X-77')->assertSee('Amina Example')->assertDontSee('Other Person');
        $this->actingAs($user)->get('/staff?q=Analyst')->assertSee('Amina Example')->assertDontSee('Other Person');
    }

    public function test_show_lists_open_tasks_of_linked_user(): void
    {
        $account = $this->userWithRole('staff');
        $member = Staff::factory()->create(['user_id' => $account->id]);
        Task::query()->create(['reference' => 'T-1', 'title' => 'Prepare filing index', 'status' => 'in_progress', 'assigned_to' => $account->id]);
        Task::query()->create(['reference' => 'T-2', 'title' => 'Old finished job', 'status' => 'completed', 'assigned_to' => $account->id]);

        $this->actingAs($this->userWithRole('director'))->get("/staff/{$member->id}")
            ->assertSee('Prepare filing index')->assertDontSee('Old finished job');
    }

    public function test_users_without_manage_permission_cannot_modify(): void
    {
        $member = Staff::factory()->create();
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get('/staff/create')->assertForbidden();
        $this->actingAs($staff)->post('/staff', $this->payload())->assertForbidden();
        $this->actingAs($staff)->put("/staff/{$member->id}", $this->payload())->assertForbidden();
        $this->actingAs($staff)->delete("/staff/{$member->id}")->assertForbidden();
        $this->actingAs($this->userWithPermissions(['dashboard.view']))->get('/staff')->assertForbidden();
    }
}
