<?php

namespace Tests\Feature\Correspondence;

use App\Models\Correspondence;
use App\Models\Department;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CorrespondenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    private function officer(): User
    {
        return $this->userWithRole('Document Officer');
    }

    /**
     * @return array<string, mixed>
     */
    private function letterData(array $overrides = []): array
    {
        return [
            'direction' => 'incoming',
            'external_reference' => 'MOF/2026/551',
            'subject' => 'Request for data sharing agreement',
            'sender' => 'Ministry of Finance',
            'recipient' => 'National Data Center Somalia',
            'letter_date' => '2026-10-10',
            'received_or_sent_date' => '2026-10-15',
            'priority' => 'normal',
            'is_confidential' => '0',
            'summary' => 'The ministry asks for a data sharing agreement.',
            ...$overrides,
        ];
    }

    public function test_registering_incoming_and_outgoing_letters_assigns_sequential_unique_references(): void
    {
        $officer = $this->officer();
        $year = now()->format('Y');

        $this->actingAs($officer)->post(route('correspondence.store'), $this->letterData([
            'scan' => UploadedFile::fake()->create('letter.pdf', 120, 'application/pdf'),
        ]))->assertRedirect();
        $this->actingAs($officer)->post(route('correspondence.store'), $this->letterData(['subject' => 'Second letter']))->assertRedirect();
        $this->actingAs($officer)->post(route('correspondence.store'), $this->letterData([
            'direction' => 'outgoing', 'subject' => 'Invitation to workshop', 'sender' => 'NDC', 'recipient' => 'Ministry of Health',
        ]))->assertRedirect();

        $first = Correspondence::query()->where('subject', 'Request for data sharing agreement')->firstOrFail();
        $second = Correspondence::query()->where('subject', 'Second letter')->firstOrFail();
        $outgoing = Correspondence::query()->where('direction', 'outgoing')->firstOrFail();

        $this->assertSame("NDC/IN/{$year}/0001", $first->reference_number);
        $this->assertSame("NDC/IN/{$year}/0002", $second->reference_number);
        $this->assertSame("NDC/OUT/{$year}/0001", $outgoing->reference_number);
        $this->assertSame('MOF/2026/551', $first->external_reference);
        $this->assertSame('received', $first->status);
        $this->assertSame('pending', $outgoing->status);
        $this->assertSame($officer->id, $first->created_by);
        $this->assertCount(1, $first->attachments);
        $this->assertDatabaseHas('correspondence_actions', ['correspondence_id' => $first->id, 'action' => 'registered']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'correspondence', 'auditable_id' => $first->id]);
    }

    public function test_registration_is_validated(): void
    {
        $this->actingAs($this->officer())
            ->post(route('correspondence.store'), ['direction' => 'sideways', 'priority' => 'normal'])
            ->assertSessionHasErrors(['direction', 'subject', 'sender', 'recipient']);

        $this->assertSame(0, Correspondence::query()->count());
    }

    public function test_assignment_at_registration_records_action_and_notifies(): void
    {
        $officer = $this->officer();
        $assignee = $this->userWithRole('Department Head');

        $this->actingAs($officer)->post(route('correspondence.store'), $this->letterData([
            'assigned_to' => $assignee->id, 'response_due_date' => today()->addWeek()->toDateString(), 'assignment_note' => 'Please draft a reply',
        ]))->assertRedirect();

        $letter = Correspondence::query()->firstOrFail();
        $this->assertSame($assignee->id, $letter->assigned_to);
        $this->assertDatabaseHas('correspondence_actions', ['correspondence_id' => $letter->id, 'action' => 'assigned', 'to_user_id' => $assignee->id, 'note' => 'Please draft a reply']);
        Notification::assertSentTo($assignee, GenericNotification::class, fn ($n) => $n->kind === 'correspondence_assigned');
    }

    public function test_assign_forward_respond_and_close_flow(): void
    {
        $officer = $this->officer();
        $head = $this->userWithRole('Department Head');
        $other = $this->userWithRole('Director or Management');
        $letter = Correspondence::factory()->incoming()->create(['created_by' => $officer->id]);

        $this->actingAs($officer)->post(route('correspondence.assign', $letter), [
            'assigned_to' => $head->id, 'response_due_date' => today()->addDays(5)->toDateString(), 'note' => 'Handle this',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $letter->refresh();
        $this->assertSame($head->id, $letter->assigned_to);
        $this->assertSame(today()->addDays(5)->toDateString(), $letter->response_due_date->toDateString());
        Notification::assertSentTo($head, GenericNotification::class, fn ($n) => $n->kind === 'correspondence_assigned');

        $this->actingAs($head)->post(route('correspondence.forward', $letter), ['to_user_id' => $other->id, 'note' => 'For your decision'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $letter->refresh();
        $this->assertSame('forwarded', $letter->status);
        $this->assertSame($other->id, $letter->assigned_to);
        Notification::assertSentTo($other, GenericNotification::class, fn ($n) => $n->kind === 'correspondence_forwarded');

        $this->actingAs($other)->post(route('correspondence.respond', $letter), [
            'mode' => 'new', 'reply_subject' => 'Re: request', 'reply_recipient' => $letter->sender,
            'reply_date' => today()->toDateString(), 'reply_sent' => '1', 'note' => 'Agreed in principle',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $letter->refresh();
        $this->assertSame('responded', $letter->status);
        $this->assertNotNull($letter->responded_at);
        $reply = Correspondence::query()->where('parent_id', $letter->id)->firstOrFail();
        $this->assertSame('outgoing', $reply->direction);
        $this->assertSame('sent', $reply->status);
        $this->assertStringStartsWith('NDC/OUT/', $reply->reference_number);

        $this->actingAs($officer)->post(route('correspondence.close', $letter), ['note' => 'Done'])->assertRedirect();
        $letter->refresh();
        $this->assertSame('closed', $letter->status);
        $this->assertNotNull($letter->closed_at);

        $this->assertSame(
            ['assigned', 'forwarded', 'responded', 'closed'],
            $letter->actions()->reorder()->orderBy('id')->pluck('action')->all(),
        );
        foreach (['assigned', 'forwarded', 'responded', 'closed'] as $event) {
            $this->assertDatabaseHas('audit_logs', ['event' => $event, 'auditable_type' => 'correspondence', 'auditable_id' => $letter->id]);
        }

        // Closed letters accept no further actions.
        $this->actingAs($officer)->post(route('correspondence.note', $letter), ['note' => 'Late note'])->assertForbidden();
    }

    public function test_response_can_link_an_existing_outgoing_letter(): void
    {
        $officer = $this->officer();
        $letter = Correspondence::factory()->incoming()->create();
        $reply = Correspondence::factory()->outgoing()->create();

        $this->actingAs($officer)->post(route('correspondence.respond', $letter), ['mode' => 'link', 'reply_id' => $reply->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($letter->id, $reply->fresh()->parent_id);
        $this->assertSame('responded', $letter->fresh()->status);
    }

    public function test_outgoing_letter_can_be_marked_sent(): void
    {
        $officer = $this->officer();
        $letter = Correspondence::factory()->outgoing()->create(['received_or_sent_date' => null]);

        $this->actingAs($officer)->post(route('correspondence.send', $letter), ['sent_date' => today()->toDateString()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $letter->refresh();
        $this->assertSame('sent', $letter->status);
        $this->assertSame(today()->toDateString(), $letter->received_or_sent_date->toDateString());
        $this->assertDatabaseHas('correspondence_actions', ['correspondence_id' => $letter->id, 'action' => 'sent']);

        // Incoming letters cannot be "sent".
        $incoming = Correspondence::factory()->incoming()->create();
        $this->actingAs($officer)->post(route('correspondence.send', $incoming))->assertForbidden();
    }

    public function test_assignee_without_update_permission_can_forward_but_not_assign(): void
    {
        $assignee = $this->userWithRole('Project Manager'); // correspondence.view only
        $other = $this->userWithRole('Document Officer');
        $letter = Correspondence::factory()->incoming()->create(['assigned_to' => $assignee->id]);

        $this->actingAs($assignee)->post(route('correspondence.assign', $letter), ['assigned_to' => $other->id])->assertForbidden();
        $this->actingAs($assignee)->post(route('correspondence.forward', $letter), ['to_user_id' => $other->id])->assertRedirect();
        $this->assertSame($other->id, $letter->fresh()->assigned_to);

        // No longer the assignee: further handling is refused.
        $this->actingAs($assignee)->post(route('correspondence.note', $letter), ['note' => 'Still here'])->assertForbidden();
    }

    public function test_confidential_letters_are_hidden_from_unauthorised_users(): void
    {
        $viewer = $this->userWithRole('Project Manager'); // correspondence.view, no view-confidential
        $assignee = $this->userWithRole('Department Head');
        $auditor = $this->userWithRole('Read-Only Auditor'); // view-confidential
        $secret = Correspondence::factory()->incoming()->confidential()->create(['subject' => 'Secret security matter', 'assigned_to' => $assignee->id]);
        $public = Correspondence::factory()->incoming()->create(['subject' => 'Ordinary public letter']);

        $this->actingAs($viewer)->get(route('correspondence.index'))
            ->assertOk()->assertSee('Ordinary public letter')->assertDontSee('Secret security matter');
        $this->actingAs($viewer)->get(route('correspondence.show', $secret))->assertForbidden();
        $this->actingAs($viewer)->get(route('correspondence.register', ['direction' => 'incoming']))
            ->assertOk()->assertDontSee('Secret security matter');

        $this->actingAs($assignee)->get(route('correspondence.index'))->assertSee('Secret security matter');
        $this->actingAs($assignee)->get(route('correspondence.show', $secret))->assertOk();
        $this->actingAs($auditor)->get(route('correspondence.show', $secret))->assertOk();
        $this->actingAs($viewer)->get(route('correspondence.show', $public))->assertOk();
    }

    public function test_overdue_and_other_filters(): void
    {
        $officer = $this->officer();
        Correspondence::factory()->incoming()->overdue()->create(['subject' => 'Late reply needed']);
        Correspondence::factory()->incoming()->create(['subject' => 'Due next week', 'response_due_date' => today()->addWeek()]);
        Correspondence::factory()->outgoing()->create(['subject' => 'Outgoing notice', 'recipient' => 'Ministry of Planning']);

        $this->actingAs($officer)->get(route('correspondence.index', ['overdue' => 1]))
            ->assertOk()->assertSee('Late reply needed')->assertDontSee('Due next week')->assertDontSee('Outgoing notice');

        $this->actingAs($officer)->get(route('correspondence.index', ['direction' => 'outgoing']))
            ->assertOk()->assertSee('Outgoing notice')->assertDontSee('Late reply needed');

        $this->actingAs($officer)->get(route('correspondence.index', ['q' => 'Planning']))
            ->assertOk()->assertSee('Outgoing notice')->assertDontSee('Due next week');

        $this->assertTrue(Correspondence::query()->where('subject', 'Late reply needed')->first()->isOverdue());
    }

    public function test_workflow_state_rules_also_apply_to_super_admin(): void
    {
        $admin = $this->superAdmin();
        $outgoing = Correspondence::factory()->outgoing()->create();
        $closed = Correspondence::factory()->incoming()->create(['status' => 'closed']);

        $this->actingAs($admin)->post(route('correspondence.respond', $outgoing), ['mode' => 'none'])->assertForbidden();
        $this->actingAs($admin)->post(route('correspondence.note', $closed), ['note' => 'x'])->assertForbidden();
        $this->actingAs($admin)->get(route('correspondence.show', $closed))->assertOk()->assertDontSee(route('correspondence.close', $closed));
    }

    public function test_general_staff_cannot_access_correspondence(): void
    {
        $staff = $this->userWithRole('General Staff');
        $letter = Correspondence::factory()->create();

        $this->actingAs($staff)->get(route('correspondence.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('correspondence.show', $letter))->assertForbidden();
        $this->actingAs($staff)->post(route('correspondence.store'), $this->letterData())->assertForbidden();
    }

    public function test_viewer_without_create_permission_cannot_register_or_edit(): void
    {
        $viewer = $this->userWithRole('Project Manager');
        $letter = Correspondence::factory()->create();

        $this->actingAs($viewer)->get(route('correspondence.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('correspondence.edit', $letter))->assertForbidden();
        $this->actingAs($viewer)->delete(route('correspondence.destroy', $letter))->assertForbidden();
    }

    public function test_update_and_delete(): void
    {
        $officer = $this->officer();
        $letter = Correspondence::factory()->incoming()->create();

        $this->actingAs($officer)->put(route('correspondence.update', $letter), $this->letterData(['subject' => 'Updated subject', 'direction' => 'outgoing']))
            ->assertRedirect(route('correspondence.show', $letter));
        $letter->refresh();
        $this->assertSame('Updated subject', $letter->subject);
        $this->assertSame('incoming', $letter->direction, 'Direction cannot change after registration.');

        $this->actingAs($officer)->delete(route('correspondence.destroy', $letter))->assertRedirect(route('correspondence.index'));
        $this->assertSoftDeleted($letter);
    }

    public function test_all_pages_render(): void
    {
        $officer = $this->officer();
        $department = Department::query()->create(['name' => 'ICT', 'code' => 'ICT']);
        $letter = Correspondence::factory()->incoming()->create(['department_id' => $department->id, 'assigned_to' => $officer->id]);
        $letter->recordAction('assigned', $officer, $officer->id, 'Please respond', today()->addDay());
        $outgoing = Correspondence::factory()->outgoing()->create(['parent_id' => $letter->id]);

        $this->actingAs($officer)->get(route('correspondence.index'))->assertOk()->assertSee($letter->reference_number);
        $this->actingAs($officer)->get(route('correspondence.create', ['direction' => 'outgoing']))->assertOk();
        $this->actingAs($officer)->get(route('correspondence.create'))->assertOk();
        $this->actingAs($officer)->get(route('correspondence.show', $letter))->assertOk()
            ->assertSee('Please respond')->assertSee($outgoing->reference_number);
        $this->actingAs($officer)->get(route('correspondence.show', $outgoing))->assertOk();
        $this->actingAs($officer)->get(route('correspondence.edit', $letter))->assertOk();
        $this->actingAs($officer)->get(route('correspondence.register', ['direction' => 'outgoing', 'from' => '2020-01-01', 'to' => today()->toDateString()]))
            ->assertOk()->assertSee($outgoing->reference_number)->assertSee('data-print', false);

        $this->actingAs($this->superAdmin())->get(route('correspondence.show', $letter))->assertOk();
    }
}
