<?php

namespace Tests\Feature\Organisation;

use App\Models\Department;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    public function test_authorised_user_can_create_update_and_archive_department(): void
    {
        $admin = $this->userWithRole('system-admin');

        $this->actingAs($admin)->post('/departments', ['code' => 'ICT', 'name' => 'ICT Department', 'is_active' => '1'])
            ->assertRedirect();
        $department = Department::query()->where('code', 'ICT')->firstOrFail();
        $this->assertSame($admin->id, $department->created_by);

        $this->actingAs($admin)->put("/departments/{$department->id}", ['code' => 'ICT', 'name' => 'Information Technology', 'is_active' => '1'])
            ->assertRedirect(route('departments.show', $department));
        $this->assertSame('Information Technology', $department->fresh()->name);

        $this->actingAs($admin)->delete("/departments/{$department->id}")->assertRedirect(route('departments.index'));
        $this->assertSoftDeleted($department);
    }

    public function test_validation_rejects_duplicate_codes_and_self_parent(): void
    {
        $admin = $this->userWithRole('system-admin');
        $existing = Department::factory()->create(['code' => 'FIN']);

        $this->actingAs($admin)->post('/departments', ['code' => 'FIN', 'name' => 'Duplicate'])->assertSessionHasErrors('code');
        $this->actingAs($admin)->put("/departments/{$existing->id}", ['code' => 'FIN', 'name' => 'X', 'parent_id' => $existing->id])->assertSessionHasErrors('parent_id');
        $this->actingAs($admin)->post('/departments', ['code' => '', 'name' => ''])->assertSessionHasErrors(['code', 'name']);
    }

    public function test_department_with_staff_cannot_be_archived(): void
    {
        $admin = $this->userWithRole('system-admin');
        $member = $this->userWithRole('staff');
        $department = $this->placeInDepartment($member);

        $this->actingAs($admin)->delete("/departments/{$department->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($department);
    }

    public function test_read_only_users_cannot_modify_departments(): void
    {
        $staff = $this->userWithRole('staff');
        $department = Department::factory()->create();

        $this->actingAs($staff)->get('/departments')->assertOk();
        $this->actingAs($staff)->get('/departments/create')->assertForbidden();
        $this->actingAs($staff)->post('/departments', ['code' => 'X1', 'name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->put("/departments/{$department->id}", ['code' => 'X1', 'name' => 'X'])->assertForbidden();
        $this->actingAs($staff)->delete("/departments/{$department->id}")->assertForbidden();
    }

    public function test_search_filters_results(): void
    {
        $admin = $this->userWithRole('system-admin');
        Department::factory()->create(['code' => 'HRD', 'name' => 'Human Resources']);
        Department::factory()->create(['code' => 'LEG', 'name' => 'Legal Affairs']);

        $this->actingAs($admin)->get('/departments?q=Legal')->assertSee('Legal Affairs')->assertDontSee('Human Resources');
    }
}
