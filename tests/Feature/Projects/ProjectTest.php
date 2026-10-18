<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\Task;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Records digitisation',
            'status' => 'planned',
            'priority' => 'high',
            'start_date' => '2026-01-01',
            'expected_end_date' => '2026-12-31',
        ], $overrides);
    }

    public function test_project_manager_can_create_project_with_unique_reference(): void
    {
        $pm = $this->userWithRole('project-manager');

        $this->actingAs($pm)->post('/projects', $this->payload(['manager_id' => $pm->id]))->assertRedirect();
        $this->actingAs($pm)->post('/projects', $this->payload(['name' => 'Second']))->assertRedirect();

        $refs = Project::query()->pluck('reference');
        $this->assertCount(2, $refs);
        $this->assertCount(2, $refs->unique());
        $this->assertMatchesRegularExpression('/^NDC-PRJ-\d{4}-\d{4}$/', $refs->first());
        $this->assertSame($pm->id, Project::query()->where('name', 'Records digitisation')->value('manager_id'));
    }

    public function test_validation_rejects_bad_input(): void
    {
        $pm = $this->userWithRole('project-manager');

        $this->actingAs($pm)->post('/projects', $this->payload(['name' => '', 'status' => 'bogus', 'expected_end_date' => '2025-01-01']))
            ->assertSessionHasErrors(['name', 'status', 'expected_end_date']);
        $this->assertSame(0, Project::query()->count());
    }

    public function test_progress_cannot_be_set_manually_and_is_derived_from_tasks(): void
    {
        $pm = $this->userWithRole('project-manager');
        $project = Project::factory()->create(['manager_id' => $pm->id]);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'completed']);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'in_progress']);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'todo']);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'cancelled']);

        $this->assertSame(33, $project->progressPercent());

        $this->actingAs($pm)->put("/projects/{$project->id}", $this->payload(['progress' => 90]))->assertRedirect();
        $this->assertSame(33, $project->fresh()->progressPercent());
    }

    public function test_project_manager_can_edit_own_project_but_not_others(): void
    {
        $pm = $this->userWithRole('project-manager');
        $own = Project::factory()->create(['manager_id' => $pm->id]);
        $other = Project::factory()->create(['manager_id' => $this->userWithRole('project-manager')->id]);

        $this->actingAs($pm)->put("/projects/{$own->id}", $this->payload(['name' => 'Renamed']))->assertRedirect(route('projects.show', $own));
        $this->assertSame('Renamed', $own->fresh()->name);

        $this->actingAs($pm)->get("/projects/{$other->id}/edit")->assertForbidden();
        $this->actingAs($pm)->put("/projects/{$other->id}", $this->payload(['name' => 'Hijacked']))->assertForbidden();
        $this->assertNotSame('Hijacked', $other->fresh()->name);
    }

    public function test_staff_cannot_create_projects(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->get('/projects')->assertOk();
        $this->actingAs($staff)->get('/projects/create')->assertForbidden();
        $this->actingAs($staff)->post('/projects', $this->payload())->assertForbidden();
        $this->assertSame(0, Project::query()->count());
    }

    public function test_users_without_project_permission_get_403(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);
        $project = Project::factory()->create();

        $this->actingAs($user)->get('/projects')->assertForbidden();
        $this->actingAs($user)->get("/projects/{$project->id}")->assertForbidden();
    }

    public function test_archived_project_is_read_only_and_can_be_restored(): void
    {
        $director = $this->userWithRole('director');
        $project = Project::factory()->create(['manager_id' => $director->id]);

        $this->actingAs($director)->post("/projects/{$project->id}/archive")->assertRedirect();
        $this->assertNotNull($project->fresh()->archived_at);

        $this->actingAs($director)->put("/projects/{$project->id}", $this->payload(['name' => 'Changed']))->assertForbidden();
        $this->actingAs($director)->post("/projects/{$project->id}/milestones", ['title' => 'M', 'status' => 'pending'])->assertForbidden();

        $this->actingAs($director)->get('/projects')->assertDontSee($project->name);
        $this->actingAs($director)->get('/projects?archived=1')->assertSee($project->name);

        $this->actingAs($director)->post("/projects/{$project->id}/restore")->assertRedirect();
        $this->assertNull($project->fresh()->archived_at);
    }

    public function test_project_manager_cannot_archive(): void
    {
        $pm = $this->userWithRole('project-manager');
        $project = Project::factory()->create(['manager_id' => $pm->id]);

        $this->actingAs($pm)->post("/projects/{$project->id}/archive")->assertForbidden();
        $this->assertNull($project->fresh()->archived_at);
    }

    public function test_index_filters_by_status_and_search(): void
    {
        $director = $this->userWithRole('director');
        Project::factory()->create(['name' => 'Alpha network', 'status' => 'in_progress']);
        Project::factory()->create(['name' => 'Beta portal', 'status' => 'planned']);

        $this->actingAs($director)->get('/projects?status=in_progress')->assertSee('Alpha network')->assertDontSee('Beta portal');
        $this->actingAs($director)->get('/projects?q=Beta')->assertSee('Beta portal')->assertDontSee('Alpha network');
    }

    public function test_show_and_report_pages_render_details(): void
    {
        $director = $this->userWithRole('director');
        $project = Project::factory()->create(['name' => 'Report me', 'objectives' => 'Clear objective text']);

        $this->actingAs($director)->get("/projects/{$project->id}")->assertOk()->assertSee('Clear objective text');
        $this->actingAs($director)->get("/projects/{$project->id}/report")->assertOk()->assertSee('Report me')->assertSee(__('projects.print_hint'));
    }
}
