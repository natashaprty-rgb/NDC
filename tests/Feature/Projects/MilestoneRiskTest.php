<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use Tests\TestCase;

class MilestoneRiskTest extends TestCase
{
    public function test_manager_can_add_complete_and_remove_milestones(): void
    {
        $pm = $this->userWithRole('project-manager');
        $project = Project::factory()->create(['manager_id' => $pm->id]);

        $this->actingAs($pm)->post("/projects/{$project->id}/milestones", ['title' => 'Design approved', 'status' => 'pending', 'due_date' => '2026-11-30'])
            ->assertRedirect();
        $milestone = $project->milestones()->firstOrFail();
        $this->assertNull($milestone->completed_at);

        $this->actingAs($pm)->put("/projects/{$project->id}/milestones/{$milestone->id}", ['title' => 'Design approved', 'status' => 'completed'])->assertRedirect();
        $this->assertNotNull($milestone->fresh()->completed_at);

        $this->actingAs($pm)->delete("/projects/{$project->id}/milestones/{$milestone->id}")->assertRedirect();
        $this->assertDatabaseMissing('project_milestones', ['id' => $milestone->id]);
    }

    public function test_milestone_cannot_be_modified_through_another_project(): void
    {
        $pm = $this->userWithRole('project-manager');
        $own = Project::factory()->create(['manager_id' => $pm->id]);
        $foreignMilestone = ProjectMilestone::factory()->create();

        $this->actingAs($pm)->put("/projects/{$own->id}/milestones/{$foreignMilestone->id}", ['title' => 'X', 'status' => 'completed'])->assertNotFound();
        $this->assertSame('pending', $foreignMilestone->fresh()->status);
    }

    public function test_non_manager_cannot_manage_details(): void
    {
        $officer = $this->userWithRole('project-officer');
        $project = Project::factory()->create();

        $this->actingAs($officer)->post("/projects/{$project->id}/milestones", ['title' => 'M', 'status' => 'pending'])->assertForbidden();
        $this->actingAs($officer)->post("/projects/{$project->id}/risks", ['type' => 'risk', 'title' => 'R', 'likelihood' => 'low', 'impact' => 'low', 'status' => 'open'])->assertForbidden();
        $this->actingAs($officer)->post("/projects/{$project->id}/stakeholders", ['type' => 'consultant', 'name' => 'S'])->assertForbidden();
        $this->assertSame(0, ProjectMilestone::query()->count());
    }

    public function test_risks_record_severity_and_closure(): void
    {
        $pm = $this->userWithRole('project-manager');
        $project = Project::factory()->create(['manager_id' => $pm->id]);

        $this->actingAs($pm)->post("/projects/{$project->id}/risks", [
            'type' => 'issue', 'title' => 'Power supply delay', 'likelihood' => 'high', 'impact' => 'high', 'status' => 'open',
            'owner_id' => $pm->id, 'corrective_action' => 'Escalate to supplier',
        ])->assertRedirect();
        $risk = ProjectRisk::query()->firstOrFail();
        $this->assertSame(9, $risk->severity());
        $this->assertSame('issue', $risk->type);

        $this->actingAs($pm)->put("/projects/{$project->id}/risks/{$risk->id}", [
            'type' => 'issue', 'title' => 'Power supply delay', 'likelihood' => 'high', 'impact' => 'high', 'status' => 'closed',
        ])->assertRedirect();
        $this->assertNotNull($risk->fresh()->closed_at);

        $this->actingAs($pm)->post("/projects/{$project->id}/risks", ['type' => 'risk', 'title' => '', 'likelihood' => 'extreme', 'impact' => 'low', 'status' => 'open'])
            ->assertSessionHasErrors(['title', 'likelihood']);
    }

    public function test_stakeholders_can_be_recorded(): void
    {
        $pm = $this->userWithRole('project-manager');
        $project = Project::factory()->create(['manager_id' => $pm->id]);

        $this->actingAs($pm)->post("/projects/{$project->id}/stakeholders", ['type' => 'consultant', 'name' => 'Jane Adviser', 'organization' => 'Advisory Ltd', 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
        $this->actingAs($pm)->post("/projects/{$project->id}/stakeholders", ['type' => 'consultant', 'name' => 'Jane Adviser', 'organization' => 'Advisory Ltd'])
            ->assertRedirect();
        $this->assertDatabaseHas('project_stakeholders', ['project_id' => $project->id, 'type' => 'consultant', 'name' => 'Jane Adviser']);
    }
}
