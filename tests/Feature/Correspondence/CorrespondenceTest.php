<?php

namespace Tests\Feature\Correspondence;

use App\Models\Correspondence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CorrespondenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'direction' => 'incoming',
            'subject' => 'Request for data hosting consultation',
            'sender' => 'Ministry of Example',
            'recipient' => 'NDC',
            'received_or_sent_date' => today()->toDateString(),
            'priority' => 'normal',
            'confidentiality' => 'internal',
        ], $overrides);
    }

    private function register(User $user, array $overrides = []): Correspondence
    {
        $this->actingAs($user)->post('/correspondence', $this->payload($overrides))->assertSessionHasNoErrors()->assertRedirect();

        return Correspondence::query()->latest('id')->firstOrFail();
    }

    public function test_registry_officer_registers_incoming_letter_with_scan_and_assignment(): void
    {
        $officer = $this->userWithRole('document-officer');
        $assignee = $this->userWithRole('project-manager');
        $png = UploadedFile::fake()->createWithContent('scan.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

        $item = $this->register($officer, ['assigned_to' => $assignee->id, 'response_deadline' => today()->addDays(7)->toDateString(), 'file' => $png]);

        $this->assertMatchesRegularExpression('/^NDC-IN-\d{4}-0001$/', $item->reference);
        $this->assertSame('assigned', $item->status);
        $this->assertSame(1, $item->attachments()->where('category', 'scan')->count());
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $assignee->id]);

        $out = $this->register($officer, ['direction' => 'outgoing', 'in_reply_to_id' => $item->id]);
        $this->assertMatchesRegularExpression('/^NDC-OUT-\d{4}-0001$/', $out->reference);
        $this->assertSame('registered', $out->status);
        $this->assertSame($item->id, $out->inReplyTo->id);
    }

    public function test_reference_numbers_are_unique_across_many_registrations(): void
    {
        $officer = $this->userWithRole('document-officer');
        for ($i = 0; $i < 8; $i++) {
            $this->register($officer, ['subject' => 'Letter '.$i]);
        }

        $refs = Correspondence::query()->pluck('reference');
        $this->assertCount(8, $refs);
        $this->assertCount(8, $refs->unique());
    }

    public function test_validation_errors(): void
    {
        $officer = $this->userWithRole('document-officer');
        $this->actingAs($officer)->post('/correspondence', $this->payload([
            'direction' => 'sideways', 'subject' => '', 'response_deadline' => today()->subDay()->toDateString(), 'priority' => 'whenever',
        ]))->assertSessionHasErrors(['direction', 'subject', 'response_deadline', 'priority']);
        $this->assertSame(0, Correspondence::query()->count());
    }

    public function test_confidential_letter_is_hidden_from_other_departments(): void
    {
        $officer = $this->userWithRole('document-officer');
        $dept = $this->placeInDepartment($this->userWithRole('project-manager'));
        $item = $this->register($officer, ['subject' => 'Sensitive security notice', 'confidentiality' => 'confidential', 'department_id' => $dept->id]);

        $outsider = $this->userWithRole('project-manager');
        $this->placeInDepartment($outsider);
        $this->actingAs($outsider)->get('/correspondence')->assertOk()->assertDontSee('Sensitive security notice');
        $this->actingAs($outsider)->get("/correspondence/{$item->id}")->assertForbidden();

        $member = $this->userWithRole('project-manager');
        $this->placeInDepartment($member, $dept);
        $this->actingAs($member)->get("/correspondence/{$item->id}")->assertOk();

        // Cannot link a reply to a letter you are not allowed to see
        $outsiderOfficer = $this->userWithRole('project-manager');
        $this->placeInDepartment($outsiderOfficer);
        $limitedRegistrar = $this->userWithPermissions(['correspondence.view', 'correspondence.manage']);
        $this->actingAs($limitedRegistrar)->post('/correspondence', $this->payload(['in_reply_to_id' => $item->id]))->assertSessionHasErrors('in_reply_to_id');
    }

    public function test_assigned_officer_can_forward_but_cannot_register(): void
    {
        $officer = $this->userWithRole('document-officer');
        $assignee = $this->userWithRole('project-manager');
        $colleague = $this->userWithRole('project-manager');
        $item = $this->register($officer, ['assigned_to' => $assignee->id]);

        $this->actingAs($assignee)->post('/correspondence', $this->payload())->assertForbidden();
        $this->actingAs($assignee)->get('/correspondence/create')->assertForbidden();

        $this->actingAs($assignee)->post("/correspondence/{$item->id}/forward", ['to_user_id' => $colleague->id, 'note' => 'Please draft reply'])
            ->assertSessionHasNoErrors();
        $this->assertSame($colleague->id, $item->fresh()->assigned_to);
        $this->assertDatabaseHas('correspondence_actions', ['correspondence_id' => $item->id, 'action' => 'forwarded', 'from_user_id' => $assignee->id, 'to_user_id' => $colleague->id]);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $colleague->id]);

        // The previous assignee no longer may act; an unrelated viewer never could.
        $this->actingAs($assignee)->post("/correspondence/{$item->id}/follow-up", ['note' => 'x'])->assertForbidden();
        $this->actingAs($this->userWithRole('auditor'))->post("/correspondence/{$item->id}/forward", ['to_user_id' => $colleague->id])->assertForbidden();

        $this->actingAs($colleague)->post("/correspondence/{$item->id}/forward", [])->assertSessionHasErrors(['to_user_id', 'to_department_id']);
    }

    public function test_follow_up_and_status_changes_are_recorded(): void
    {
        $officer = $this->userWithRole('document-officer');
        $assignee = $this->userWithRole('project-manager');
        $item = $this->register($officer, ['assigned_to' => $assignee->id]);

        $this->actingAs($assignee)->post("/correspondence/{$item->id}/follow-up", ['note' => 'Called sender', 'follow_up_date' => today()->addDays(3)->toDateString()])
            ->assertSessionHasNoErrors();
        $this->actingAs($assignee)->post("/correspondence/{$item->id}/status", ['status' => 'closed', 'note' => 'Done'])->assertSessionHasNoErrors();

        $fresh = $item->fresh();
        $this->assertSame('closed', $fresh->status);
        $this->assertNotNull($fresh->closed_at);
        $this->assertDatabaseHas('correspondence_actions', ['correspondence_id' => $item->id, 'action' => 'status_change', 'from_status' => 'assigned', 'to_status' => 'closed']);
        $this->assertDatabaseHas('correspondence_actions', ['correspondence_id' => $item->id, 'action' => 'follow_up', 'note' => 'Called sender']);

        // Closed: only registry officers may act further
        $this->actingAs($assignee)->post("/correspondence/{$item->id}/status", ['status' => 'in_progress'])->assertForbidden();
        $this->actingAs($officer)->post("/correspondence/{$item->id}/status", ['status' => 'closed'])->assertSessionHasErrors('status');
        $this->actingAs($officer)->post("/correspondence/{$item->id}/status", ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->assertNull($item->fresh()->closed_at);
    }

    public function test_overdue_and_direction_filters(): void
    {
        $officer = $this->userWithRole('document-officer');
        $this->register($officer, ['subject' => 'Late reply needed', 'received_or_sent_date' => today()->subDays(10)->toDateString(), 'response_deadline' => today()->subDays(2)->toDateString()]);
        $this->register($officer, ['subject' => 'On time letter', 'response_deadline' => today()->addDays(5)->toDateString()]);
        $this->register($officer, ['subject' => 'Our outgoing letter', 'direction' => 'outgoing']);

        $this->actingAs($officer)->get('/correspondence?overdue=1')->assertSee('Late reply needed')->assertDontSee('On time letter');
        $this->actingAs($officer)->get('/correspondence?direction=outgoing')->assertSee('Our outgoing letter')->assertDontSee('On time letter');
        $this->actingAs($officer)->get('/correspondence?q=Late')->assertSee('Late reply needed')->assertDontSee('Our outgoing letter');
    }

    public function test_edit_and_soft_delete_by_registry_officer_only(): void
    {
        $officer = $this->userWithRole('document-officer');
        $item = $this->register($officer);

        $this->actingAs($officer)->put("/correspondence/{$item->id}", $this->payload(['subject' => 'Corrected subject']))->assertRedirect();
        $this->assertSame('Corrected subject', $item->fresh()->subject);
        $this->assertSame('incoming', $item->fresh()->direction);

        $this->actingAs($this->userWithRole('project-manager'))->delete("/correspondence/{$item->id}")->assertForbidden();
        $this->actingAs($officer)->delete("/correspondence/{$item->id}")->assertRedirect(route('correspondence.index'));
        $this->assertSoftDeleted($item);
    }

    public function test_pages_render_for_viewers(): void
    {
        $officer = $this->userWithRole('document-officer');
        $item = $this->register($officer);

        $this->actingAs($officer)->get('/correspondence/create')->assertOk();
        $this->actingAs($officer)->get("/correspondence/{$item->id}")->assertOk()->assertSee($item->reference);
        $this->actingAs($officer)->get("/correspondence/{$item->id}/edit")->assertOk();
        $this->actingAs($this->userWithRole('staff'))->get('/correspondence')->assertForbidden();
    }
}
