<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\ProjectStakeholder;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Milestones, risk register, stakeholders, team members and documents.
 */
class ProjectComponentsTest extends TestCase
{
    use RefreshDatabase;

    private User $pm;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pm = $this->userWithRole('Project Manager');
        $this->project = Project::factory()->create();
    }

    public function test_milestone_crud_and_completion(): void
    {
        $this->actingAs($this->pm)->post(route('projects.milestones.store', $this->project), [
            'title' => 'Requirements signed off',
            'deliverables' => 'Signed SRS',
            'due_date' => '2026-11-30',
            'status' => 'pending',
        ])->assertRedirect(route('projects.show', [$this->project, 'tab' => 'milestones']));

        $milestone = $this->project->milestones()->firstOrFail();
        $this->assertSame(1, $milestone->sort_order);
        $this->assertNull($milestone->completed_at);

        $this->actingAs($this->pm)->put(route('projects.milestones.update', [$this->project, $milestone]), [
            'title' => 'Requirements approved', 'status' => 'in_progress', 'due_date' => '2026-12-15',
        ])->assertRedirect();
        $this->assertSame('Requirements approved', $milestone->fresh()->title);

        $this->actingAs($this->pm)->post(route('projects.milestones.complete', [$this->project, $milestone]))->assertRedirect();
        $milestone->refresh();
        $this->assertSame('completed', $milestone->status);
        $this->assertNotNull($milestone->completed_at);

        $this->actingAs($this->pm)->get(route('projects.show', [$this->project, 'tab' => 'milestones']))
            ->assertOk()->assertSee('Requirements approved');

        $this->actingAs($this->pm)->delete(route('projects.milestones.destroy', [$this->project, $milestone]))->assertRedirect();
        $this->assertModelMissing($milestone);
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_type' => 'project_milestone']);
    }

    public function test_milestone_validation(): void
    {
        $this->actingAs($this->pm)->post(route('projects.milestones.store', $this->project), ['title' => '', 'status' => 'nope'])
            ->assertSessionHasErrors(['title', 'status']);
    }

    public function test_milestone_of_another_project_is_not_reachable(): void
    {
        $foreign = ProjectMilestone::factory()->create();

        $this->actingAs($this->pm)->get(route('projects.milestones.edit', [$this->project, $foreign]))->assertNotFound();
        $this->actingAs($this->pm)->delete(route('projects.milestones.destroy', [$this->project, $foreign]))->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_risk_register_crud(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($this->pm)->post(route('projects.risks.store', $this->project), [
            'type' => 'delay',
            'title' => 'Late delivery of servers',
            'likelihood' => 'high',
            'impact' => 'high',
            'status' => 'open',
            'corrective_action' => 'Escalate to supplier',
            'owner_id' => $owner->id,
            'identified_on' => '2026-10-01',
        ])->assertRedirect(route('projects.show', [$this->project, 'tab' => 'risks']));

        $risk = $this->project->risks()->firstOrFail();
        $this->assertSame('delay', $risk->type);

        $this->actingAs($this->pm)->put(route('projects.risks.update', [$this->project, $risk]), [
            'type' => 'delay', 'title' => 'Late delivery of servers', 'likelihood' => 'high', 'impact' => 'high',
            'status' => 'closed', 'identified_on' => '2026-10-01',
        ])->assertRedirect();
        $risk->refresh();
        $this->assertSame('closed', $risk->status);
        $this->assertTrue($risk->resolved_on->isToday());

        $this->actingAs($this->pm)->get(route('projects.show', [$this->project, 'tab' => 'risks']))->assertOk()->assertSee('Late delivery of servers');

        $this->actingAs($this->pm)->delete(route('projects.risks.destroy', [$this->project, $risk]))->assertRedirect();
        $this->assertModelMissing($risk);
    }

    public function test_risk_validation(): void
    {
        $this->actingAs($this->pm)->post(route('projects.risks.store', $this->project), [
            'type' => 'meteor', 'title' => '', 'likelihood' => 'extreme', 'impact' => 'medium', 'status' => 'open',
            'identified_on' => '2026-10-10', 'resolved_on' => '2026-10-01',
        ])->assertSessionHasErrors(['type', 'title', 'likelihood', 'resolved_on']);
    }

    public function test_stakeholder_crud(): void
    {
        $this->actingAs($this->pm)->post(route('projects.stakeholders.store', $this->project), [
            'type' => 'contractor', 'name' => 'Hormuud Builders', 'organization' => 'Hormuud Ltd',
            'email' => 'info@builders.test', 'phone' => '+252 61 0000000',
        ])->assertRedirect(route('projects.show', [$this->project, 'tab' => 'stakeholders']));

        $stakeholder = $this->project->stakeholders()->firstOrFail();

        $this->actingAs($this->pm)->put(route('projects.stakeholders.update', [$this->project, $stakeholder]), [
            'type' => 'consultant', 'name' => 'Hormuud Consulting',
        ])->assertRedirect();
        $this->assertSame('consultant', $stakeholder->fresh()->type);

        $this->actingAs($this->pm)->post(route('projects.stakeholders.store', $this->project), ['type' => 'contact', 'name' => 'X', 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');

        $this->actingAs($this->pm)->delete(route('projects.stakeholders.destroy', [$this->project, $stakeholder]))->assertRedirect();
        $this->assertModelMissing($stakeholder);
    }

    public function test_stakeholder_of_another_project_cannot_be_changed(): void
    {
        $foreign = ProjectStakeholder::factory()->create();

        $this->actingAs($this->pm)->put(route('projects.stakeholders.update', [$this->project, $foreign]), ['type' => 'contact', 'name' => 'Hijack'])
            ->assertNotFound();
        $this->assertNotSame('Hijack', $foreign->fresh()->name);
    }

    public function test_team_members_can_be_added_and_removed(): void
    {
        Notification::fake();
        $member = User::factory()->create();

        $this->actingAs($this->pm)->post(route('projects.members.store', $this->project), ['user_id' => $member->id, 'role' => 'Data analyst'])
            ->assertRedirect(route('projects.show', [$this->project, 'tab' => 'team']));

        $this->assertTrue($this->project->members()->whereKey($member->id)->exists());
        $this->assertSame('Data analyst', $this->project->members()->first()->pivot->role);
        $this->assertDatabaseHas('audit_logs', ['event' => 'member_added', 'auditable_type' => 'project', 'auditable_id' => $this->project->id]);
        Notification::assertSentTo($member, GenericNotification::class, fn ($n) => $n->kind === 'project_member_added');

        // Adding the same user twice is rejected.
        $this->actingAs($this->pm)->post(route('projects.members.store', $this->project), ['user_id' => $member->id])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($this->pm)->get(route('projects.show', [$this->project, 'tab' => 'team']))->assertOk()->assertSee($member->name);

        $this->actingAs($this->pm)->delete(route('projects.members.destroy', [$this->project, $member]))->assertRedirect();
        $this->assertFalse($this->project->members()->whereKey($member->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['event' => 'member_removed', 'auditable_type' => 'project']);
    }

    public function test_inactive_users_cannot_be_added_to_team(): void
    {
        $suspended = User::factory()->suspended()->create();

        $this->actingAs($this->pm)->post(route('projects.members.store', $this->project), ['user_id' => $suspended->id])
            ->assertSessionHasErrors('user_id');
    }

    public function test_users_without_update_permission_cannot_manage_components(): void
    {
        $auditor = $this->userWithRole('Read-Only Auditor');
        $milestone = ProjectMilestone::factory()->for($this->project)->create();
        $risk = ProjectRisk::factory()->for($this->project)->create();

        $this->actingAs($auditor)->post(route('projects.milestones.store', $this->project), ['title' => 'X', 'status' => 'pending'])->assertForbidden();
        $this->actingAs($auditor)->post(route('projects.milestones.complete', [$this->project, $milestone]))->assertForbidden();
        $this->actingAs($auditor)->delete(route('projects.risks.destroy', [$this->project, $risk]))->assertForbidden();
        $this->actingAs($auditor)->post(route('projects.stakeholders.store', $this->project), ['type' => 'contact', 'name' => 'X'])->assertForbidden();
        $this->actingAs($auditor)->post(route('projects.members.store', $this->project), ['user_id' => $auditor->id])->assertForbidden();

        $this->actingAs($auditor)->get(route('projects.show', [$this->project, 'tab' => 'milestones']))->assertOk()
            ->assertDontSee(route('projects.milestones.store', $this->project));
    }

    public function test_documents_can_be_uploaded_and_downloaded_on_a_project(): void
    {
        Storage::fake('local');

        $this->actingAs($this->pm)->post(route('attachments.store'), [
            'attachable_type' => 'project',
            'attachable_id' => $this->project->id,
            'description' => 'Inception report',
            'file' => UploadedFile::fake()->create('inception-report.pdf', 120, 'application/pdf'),
        ])->assertRedirect();

        $attachment = $this->project->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($this->pm)->get(route('projects.show', [$this->project, 'tab' => 'files']))->assertOk()->assertSee('inception-report.pdf');
        $this->actingAs($this->pm)->get(route('attachments.download', $attachment))->assertOk();

        // Read-only users can download but not upload.
        $auditor = $this->userWithRole('Read-Only Auditor');
        $this->actingAs($auditor)->get(route('attachments.download', $attachment))->assertOk();
        $this->actingAs($auditor)->post(route('attachments.store'), [
            'attachable_type' => 'project', 'attachable_id' => $this->project->id,
            'file' => UploadedFile::fake()->create('other.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        // General staff cannot download project documents.
        $this->actingAs($this->userWithRole('General Staff'))->get(route('attachments.download', $attachment))->assertForbidden();
    }
}
