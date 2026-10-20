<?php

namespace Tests\Feature\Staff;

use App\Models\Department;
use App\Models\Staff;
use App\Models\StaffTraining;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private function hrOfficer(): User
    {
        return $this->userWithPermissions(['staff.view', 'staff.create', 'staff.update', 'staff.delete', 'staff.view-sensitive']);
    }

    private function department(string $code = 'ICT', array $attributes = []): Department
    {
        return Department::query()->create(['name' => $code.' Department', 'code' => $code] + $attributes);
    }

    public function test_pages_render(): void
    {
        $user = $this->superAdmin();
        $staff = Staff::factory()->withSensitive()->create(['department_id' => $this->department()->id]);
        $training = StaffTraining::factory()->create(['staff_id' => $staff->id]);

        $this->actingAs($user)->get(route('staff.index'))->assertOk()->assertSee($staff->full_name);
        $this->actingAs($user)->get(route('staff.index', ['department' => $staff->department_id, 'sort' => 'hire_date', 'dir' => 'asc']))->assertOk();
        $this->actingAs($user)->get(route('staff.directory'))->assertOk()->assertSee($staff->full_name)->assertSee('ICT Department');
        $this->actingAs($user)->get(route('staff.create'))->assertOk();
        $this->actingAs($user)->get(route('staff.show', $staff))->assertOk()->assertSee($training->title);
        $this->actingAs($user)->get(route('staff.edit', $staff))->assertOk();
        $this->actingAs($user)->get(route('staff.trainings.edit', [$staff, $training]))->assertOk();
    }

    public function test_hr_can_register_staff_with_automatic_number(): void
    {
        $hr = $this->hrOfficer();
        $dept = $this->department();
        $account = User::factory()->create();

        $this->actingAs($hr)->post(route('staff.store'), [
            'full_name' => 'Hodan Abdi Warsame',
            'job_title' => 'Database Administrator',
            'department_id' => $dept->id,
            'user_id' => $account->id,
            'email' => 'hodan@ndc.gov.so',
            'employment_status' => 'active',
            'employment_type' => 'contract',
            'hire_date' => '2024-02-01',
            'gender' => 'female',
            'national_id' => 'SO-123456789',
            'date_of_birth' => '1990-05-04',
        ])->assertRedirect();

        $staff = Staff::query()->where('full_name', 'Hodan Abdi Warsame')->firstOrFail();
        $this->assertSame('NDC/STF/'.now()->year.'/0001', $staff->staff_number);
        $this->assertSame($account->id, $staff->user_id);
        $this->assertSame('SO-123456789', $staff->national_id);
        $this->assertSame('1990-05-04', $staff->date_of_birth);
        $this->assertSame($hr->id, $staff->created_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'staff', 'auditable_id' => $staff->id]);
    }

    public function test_validation_errors(): void
    {
        $hr = $this->hrOfficer();
        $existing = Staff::factory()->create(['staff_number' => 'STF-1']);
        $linked = Staff::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($hr)->post(route('staff.store'), [
            'staff_number' => 'STF-1',
            'full_name' => '',
            'email' => 'not-an-email',
            'employment_status' => 'sacked',
            'employment_type' => 'permanent',
            'hire_date' => '2024-01-10',
            'end_date' => '2023-01-01',
            'user_id' => $linked->user_id,
        ])->assertSessionHasErrors(['staff_number', 'full_name', 'email', 'employment_status', 'end_date', 'user_id']);

        $this->actingAs($hr)->put(route('staff.update', $existing), [
            'staff_number' => 'STF-1', 'full_name' => 'X', 'employment_status' => 'active', 'employment_type' => 'permanent',
            'supervisor_id' => $existing->id,
        ])->assertSessionHasErrors('supervisor_id');
    }

    public function test_hr_can_update_and_delete_staff(): void
    {
        $hr = $this->hrOfficer();
        $staff = Staff::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($hr)->put(route('staff.update', $staff), [
            'staff_number' => $staff->staff_number, 'full_name' => 'Renamed Person',
            'employment_status' => 'on_leave', 'employment_type' => 'permanent',
        ])->assertRedirect(route('staff.show', $staff));
        $this->assertSame('Renamed Person', $staff->fresh()->full_name);
        $this->assertSame('on_leave', $staff->fresh()->employment_status);

        $this->actingAs($hr)->delete(route('staff.destroy', $staff))->assertRedirect(route('staff.index'));
        $this->assertSoftDeleted('staff', ['id' => $staff->id]);
        $this->assertNull(Staff::withTrashed()->find($staff->id)->user_id);
    }

    public function test_general_staff_cannot_create_edit_or_delete(): void
    {
        $user = $this->userWithRole('General Staff');
        $staff = Staff::factory()->create();

        $this->actingAs($user)->get(route('staff.index'))->assertOk();
        $this->actingAs($user)->get(route('staff.show', $staff))->assertOk();
        $this->actingAs($user)->get(route('staff.create'))->assertForbidden();
        $this->actingAs($user)->post(route('staff.store'), [
            'full_name' => 'Nope', 'employment_status' => 'active', 'employment_type' => 'permanent',
        ])->assertForbidden();
        $this->actingAs($user)->get(route('staff.edit', $staff))->assertForbidden();
        $this->actingAs($user)->delete(route('staff.destroy', $staff))->assertForbidden();
        $this->actingAs($user)->post(route('staff.trainings.store', $staff), ['title' => 'X'])->assertForbidden();
        $this->assertDatabaseMissing('staff', ['full_name' => 'Nope']);
    }

    public function test_users_without_staff_access_are_blocked(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($user)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($user)->get(route('staff.directory'))->assertForbidden();
    }

    public function test_sensitive_fields_hidden_without_permission(): void
    {
        $staff = Staff::factory()->create([
            'national_id' => 'NID-SECRET-998877',
            'personal_phone' => '+252 61 0000001',
            'address' => 'Hodan District, House 12',
        ]);
        $viewer = $this->userWithRole('General Staff');
        $editor = $this->userWithPermissions(['staff.view', 'staff.update']);

        $this->actingAs($viewer)->get(route('staff.show', $staff))->assertOk()
            ->assertDontSee('NID-SECRET-998877')->assertDontSee('+252 61 0000001')->assertDontSee('Hodan District')
            ->assertDontSee(__('Sensitive personal information'));

        $this->actingAs($editor)->get(route('staff.edit', $staff))->assertOk()
            ->assertDontSee('NID-SECRET-998877')->assertDontSee('name="national_id"', false);

        $director = $this->userWithRole('Director or Management');
        $this->actingAs($director)->get(route('staff.show', $staff))->assertOk()->assertSee('NID-SECRET-998877');
    }

    public function test_sensitive_fields_preserved_when_edited_without_permission(): void
    {
        $staff = Staff::factory()->create(['national_id' => 'NID-KEEP', 'address' => 'Keep this address']);
        $editor = $this->userWithPermissions(['staff.view', 'staff.update']);

        $this->actingAs($editor)->put(route('staff.update', $staff), [
            'staff_number' => $staff->staff_number, 'full_name' => 'Updated Name',
            'employment_status' => 'active', 'employment_type' => 'permanent',
            'national_id' => 'HACKED', 'address' => '',
        ])->assertRedirect();

        $staff->refresh();
        $this->assertSame('Updated Name', $staff->full_name);
        $this->assertSame('NID-KEEP', $staff->national_id);
        $this->assertSame('Keep this address', $staff->address);
    }

    public function test_sensitive_fields_are_encrypted_at_rest_and_masked_in_audit(): void
    {
        $hr = $this->hrOfficer();
        $this->actingAs($hr)->post(route('staff.store'), [
            'full_name' => 'Encrypted Person', 'employment_status' => 'active', 'employment_type' => 'permanent',
            'national_id' => 'PLAIN-ID-42', 'personal_phone' => '+252 61 5555555',
        ])->assertRedirect();

        $staff = Staff::query()->where('full_name', 'Encrypted Person')->firstOrFail();
        $raw = DB::table('staff')->where('id', $staff->id)->first();

        $this->assertNotSame('PLAIN-ID-42', $raw->national_id);
        $this->assertStringNotContainsString('PLAIN-ID-42', (string) $raw->national_id);
        $this->assertStringNotContainsString('5555555', (string) $raw->personal_phone);
        $this->assertSame('PLAIN-ID-42', $staff->national_id);

        $log = DB::table('audit_logs')->where('auditable_type', 'staff')->where('auditable_id', $staff->id)->where('event', 'created')->first();
        $this->assertStringNotContainsString('PLAIN-ID-42', (string) $log->new_values);
    }

    public function test_training_crud(): void
    {
        $hr = $this->hrOfficer();
        $staff = Staff::factory()->create();

        $this->actingAs($hr)->post(route('staff.trainings.store', $staff), [
            'title' => 'ISO 27001 Lead Implementer', 'provider' => 'PECB', 'type' => 'certification',
            'start_date' => '2025-03-01', 'end_date' => '2025-03-05', 'certificate_number' => 'ISO-777',
        ])->assertRedirect();
        $training = $staff->trainings()->firstOrFail();
        $this->assertSame('ISO 27001 Lead Implementer', $training->title);

        $this->actingAs($hr)->post(route('staff.trainings.store', $staff), [
            'title' => 'Bad dates', 'type' => 'bogus', 'start_date' => '2025-03-05', 'end_date' => '2025-03-01',
        ])->assertSessionHasErrors(['type', 'end_date']);

        $this->actingAs($hr)->put(route('staff.trainings.update', [$staff, $training]), [
            'title' => 'ISO 27001 Lead Auditor', 'type' => 'certification',
        ])->assertRedirect();
        $this->assertSame('ISO 27001 Lead Auditor', $training->fresh()->title);

        $this->actingAs($hr)->get(route('staff.show', $staff))->assertSee('ISO 27001 Lead Auditor');

        $this->actingAs($hr)->delete(route('staff.trainings.destroy', [$staff, $training]))->assertRedirect();
        $this->assertDatabaseMissing('staff_trainings', ['id' => $training->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'training_removed', 'auditable_type' => 'staff', 'auditable_id' => $staff->id]);
    }

    public function test_training_routes_are_scoped_to_their_staff_member(): void
    {
        $hr = $this->hrOfficer();
        $staff = Staff::factory()->create();
        $other = StaffTraining::factory()->create();

        $this->actingAs($hr)->delete(route('staff.trainings.destroy', [$staff, $other]))->assertNotFound();
        $this->assertDatabaseHas('staff_trainings', ['id' => $other->id]);
    }

    public function test_directory_filters_and_department_head(): void
    {
        $head = User::factory()->create(['name' => 'Head Person']);
        $ict = $this->department('ICT', ['head_user_id' => $head->id]);
        $fin = $this->department('FIN');
        $a = Staff::factory()->create(['full_name' => 'Alpha Member', 'department_id' => $ict->id]);
        $b = Staff::factory()->create(['full_name' => 'Bravo Member', 'department_id' => $fin->id, 'employment_type' => 'contract']);
        $user = $this->userWithRole('General Staff');

        $this->actingAs($user)->get(route('staff.index', ['department' => $ict->id]))
            ->assertOk()->assertSee('Alpha Member')->assertDontSee('Bravo Member')->assertSee('Head Person');
        $this->actingAs($user)->get(route('staff.index', ['type' => 'contract']))
            ->assertSee('Bravo Member')->assertDontSee('Alpha Member');
        $this->actingAs($user)->get(route('staff.index', ['q' => $a->staff_number]))
            ->assertSee('Alpha Member')->assertDontSee('Bravo Member');
        $this->actingAs($user)->get(route('staff.directory', ['department' => $fin->id]))
            ->assertSee('Bravo Member')->assertDontSee('Alpha Member');
    }

    public function test_show_lists_tasks_of_linked_user(): void
    {
        $account = User::factory()->create();
        $staff = Staff::factory()->create(['user_id' => $account->id]);
        Task::query()->create(['title' => 'Migrate DNS servers', 'assigned_to' => $account->id, 'status' => 'pending', 'priority' => 'medium']);

        $viewer = $this->userWithRole('Director or Management');
        $this->actingAs($viewer)->get(route('staff.show', $staff))->assertOk()->assertSee('Migrate DNS servers');
    }

    public function test_staff_attachments_are_restricted(): void
    {
        Storage::fake('local');
        $hr = $this->hrOfficer();
        $staff = Staff::factory()->create();

        $this->actingAs($hr)->post(route('attachments.store'), [
            'attachable_type' => 'staff', 'attachable_id' => $staff->id,
            'file' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $attachment = $staff->attachments()->firstOrFail();

        $this->actingAs($hr)->get(route('attachments.download', $attachment))->assertOk();

        $viewer = $this->userWithRole('General Staff');
        $this->actingAs($viewer)->get(route('staff.show', $staff))->assertOk()->assertDontSee('cv.pdf');
        $this->actingAs($viewer)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_pages_render_in_somali(): void
    {
        $user = $this->superAdmin(['locale' => 'so']);
        $staff = Staff::factory()->create();

        $this->actingAs($user)->get(route('staff.index'))->assertOk()->assertSee('Lambarka shaqaalaha');
        $this->actingAs($user)->get(route('staff.show', $staff))->assertOk()->assertSee('Tababarka iyo horumarinta xirfadda');
    }
}
