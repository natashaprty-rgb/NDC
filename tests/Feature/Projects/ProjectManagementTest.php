<?php

namespace Tests\Feature\Projects;

use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectRisk;
use App\Models\ProjectStakeholder;
use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'National ID data platform',
            'description' => 'Build the platform.',
            'objectives' => 'Improve service delivery.',
            'location' => 'Mogadishu',
            'status' => 'planned',
            'priority' => 'high',
            'start_date' => '2026-01-01',
            'expected_end_date' => '2026-12-31',
            'planned_budget' => '250000',
            'currency' => 'usd',
            'progress' => 0,
        ], $overrides);
    }

    public function test_every_project_page_renders(): void
    {
        $admin = $this->superAdmin();
        $project = Project::factory()->create(['manager_id' => $admin->id]);
        $milestone = ProjectMilestone::factory()->for($project)->create();
        $risk = ProjectRisk::factory()->for($project)->create();
        $stakeholder = ProjectStakeholder::factory()->for($project)->create();
        $project->members()->attach($admin->id, ['role' => 'Lead']);
        Task::factory()->create(['project_id' => $project->id, 'milestone_id' => $milestone->id]);

        $this->actingAs($admin)->get(route('projects.index'))->assertOk()->assertSee($project->title);
        $this->actingAs($admin)->get(route('projects.create'))->assertOk();
        $this->actingAs($admin)->get(route('projects.edit', $project))->assertOk();
        $this->actingAs($admin)->get(route('projects.report', $project))->assertOk()->assertSee('data-print', false);
        $this->actingAs($admin)->get(route('projects.milestones.edit', [$project, $milestone]))->assertOk();
        $this->actingAs($admin)->get(route('projects.risks.edit', [$project, $risk]))->assertOk();
        $this->actingAs($admin)->get(route('projects.stakeholders.edit', [$project, $stakeholder]))->assertOk();

        foreach (['overview', 'milestones', 'risks', 'stakeholders', 'team', 'tasks', 'files', 'history'] as $tab) {
            $this->actingAs($admin)->get(route('projects.show', [$project, 'tab' => $tab]))->assertOk();
        }
    }

    public function test_pages_render_in_somali(): void
    {
        $admin = $this->superAdmin(['locale' => 'so']);
        $project = Project::factory()->create();

        $this->actingAs($admin)->withSession(['locale' => 'so'])->get(route('projects.show', $project))->assertOk();
        $this->actingAs($admin)->withSession(['locale' => 'so'])->get(route('projects.index'))->assertOk();
    }

    public function test_project_manager_can_create_project_with_generated_code(): void
    {
        Notification::fake();
        $pm = $this->userWithRole('Project Manager');
        $manager = User::factory()->create();
        $department = Department::query()->create(['name' => 'ICT', 'code' => 'ICT']);

        $this->actingAs($pm)->post(route('projects.store'), $this->validPayload([
            'manager_id' => $manager->id,
            'department_id' => $department->id,
        ]))->assertRedirect();

        $project = Project::query()->firstOrFail();
        $this->assertSame('NDC/PRJ/'.now()->year.'/0001', $project->code);
        $this->assertSame('USD', $project->currency);
        $this->assertSame($pm->id, $project->created_by);
        $this->assertSame($department->id, $project->department_id);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'project', 'auditable_id' => $project->id]);
        Notification::assertSentTo($manager, GenericNotification::class, fn ($n) => $n->kind === 'project_assigned');
    }

    public function test_manual_code_is_kept_and_must_be_unique(): void
    {
        $pm = $this->userWithRole('Project Manager');
        Project::factory()->create(['code' => 'ICT-001']);

        $this->actingAs($pm)->post(route('projects.store'), $this->validPayload(['code' => 'ICT-001']))
            ->assertSessionHasErrors('code');

        $this->actingAs($pm)->post(route('projects.store'), $this->validPayload(['code' => 'ICT-002']))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('projects', ['code' => 'ICT-002']);
    }

    public function test_generated_code_skips_codes_already_taken(): void
    {
        $pm = $this->userWithRole('Project Manager');
        Project::factory()->create(['code' => 'NDC/PRJ/'.now()->year.'/0001']);

        $this->actingAs($pm)->post(route('projects.store'), $this->validPayload())->assertSessionHasNoErrors();

        $this->assertDatabaseHas('projects', ['code' => 'NDC/PRJ/'.now()->year.'/0002']);
    }

    public function test_validation_rules_are_enforced(): void
    {
        $pm = $this->userWithRole('Project Manager');

        $this->actingAs($pm)->post(route('projects.store'), $this->validPayload([
            'title' => '',
            'status' => 'unknown',
            'expected_end_date' => '2025-01-01',
            'progress' => 150,
            'planned_budget' => -5,
            'currency' => 'US',
            'code' => 'bad code!',
        ]))->assertSessionHasErrors(['title', 'status', 'expected_end_date', 'progress', 'planned_budget', 'currency', 'code']);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_update_records_changes_and_completion_date(): void
    {
        Notification::fake();
        $pm = $this->userWithRole('Project Manager');
        $newManager = User::factory()->create();
        $project = Project::factory()->create(['code' => 'P-1', 'status' => 'in_progress', 'actual_end_date' => null]);

        $this->actingAs($pm)->put(route('projects.update', $project), $this->validPayload([
            'code' => 'P-1', 'status' => 'completed', 'progress' => 100, 'manager_id' => $newManager->id,
        ]))->assertRedirect(route('projects.show', $project));

        $project->refresh();
        $this->assertSame('completed', $project->status);
        $this->assertSame(100, $project->progress);
        $this->assertTrue($project->actual_end_date->isToday());
        $this->assertDatabaseHas('audit_logs', ['event' => 'updated', 'auditable_type' => 'project', 'auditable_id' => $project->id]);
        Notification::assertSentTo($newManager, GenericNotification::class);
    }

    public function test_general_staff_cannot_view_or_create_projects(): void
    {
        $staff = $this->userWithRole('General Staff');
        $project = Project::factory()->create();

        $this->actingAs($staff)->get(route('projects.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('projects.show', $project))->assertForbidden();
        $this->actingAs($staff)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('projects.store'), $this->validPayload())->assertForbidden();
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_viewer_without_update_permission_cannot_edit(): void
    {
        $auditor = $this->userWithRole('Read-Only Auditor');
        $project = Project::factory()->create();

        $this->actingAs($auditor)->get(route('projects.show', $project))->assertOk()->assertDontSee(route('projects.edit', $project));
        $this->actingAs($auditor)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($auditor)->put(route('projects.update', $project), $this->validPayload(['code' => $project->code]))->assertForbidden();
        $this->actingAs($auditor)->post(route('projects.archive', $project))->assertForbidden();
        $this->actingAs($auditor)->delete(route('projects.destroy', $project))->assertForbidden();
    }

    public function test_project_officer_can_edit_but_not_create_or_archive(): void
    {
        $officer = $this->userWithRole('Project Officer');
        $project = Project::factory()->create(['code' => 'P-9']);

        $this->actingAs($officer)->get(route('projects.create'))->assertForbidden();
        $this->actingAs($officer)->put(route('projects.update', $project), $this->validPayload(['code' => 'P-9', 'title' => 'Renamed']))
            ->assertRedirect();
        $this->assertSame('Renamed', $project->fresh()->title);
        $this->actingAs($officer)->post(route('projects.archive', $project))->assertForbidden();
    }

    public function test_archive_hides_project_and_makes_it_read_only_until_unarchived(): void
    {
        $director = $this->userWithRole('Director or Management');
        $project = Project::factory()->create(['title' => 'Archivable project']);
        $other = Project::factory()->create(['title' => 'Still active project']);

        $this->actingAs($director)->post(route('projects.archive', $project))->assertRedirect();
        $project->refresh();
        $this->assertTrue($project->isArchived());
        $this->assertDatabaseHas('audit_logs', ['event' => 'archived', 'auditable_type' => 'project', 'auditable_id' => $project->id]);

        $this->actingAs($director)->get(route('projects.index'))->assertOk()
            ->assertDontSee('Archivable project')->assertSee('Still active project');
        $this->actingAs($director)->get(route('projects.index', ['archived' => 'archived']))->assertOk()
            ->assertSee('Archivable project')->assertDontSee('Still active project');
        $this->actingAs($director)->get(route('projects.index', ['archived' => 'all']))->assertOk()
            ->assertSee('Archivable project')->assertSee('Still active project');

        // Archived projects are read-only.
        $this->actingAs($director)->get(route('projects.edit', $project))->assertForbidden();
        $this->actingAs($director)->post(route('projects.milestones.store', $project), ['title' => 'X', 'status' => 'pending'])->assertForbidden();
        $this->actingAs($director)->post(route('projects.archive', $project))->assertForbidden();

        $this->actingAs($director)->post(route('projects.unarchive', $project))->assertRedirect();
        $this->assertFalse($project->fresh()->isArchived());
        $this->assertDatabaseHas('audit_logs', ['event' => 'unarchived', 'auditable_type' => 'project', 'auditable_id' => $project->id]);
        $this->assertNotNull($other->fresh());
    }

    public function test_director_can_delete_project(): void
    {
        $director = $this->userWithRole('Director or Management');
        $project = Project::factory()->create();

        $this->actingAs($director)->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted($project);
        $this->actingAs($director)->get(route('projects.show', $project))->assertNotFound();
    }

    public function test_index_filters_by_status_department_and_delay(): void
    {
        $pm = $this->userWithRole('Project Manager');
        $ict = Department::query()->create(['name' => 'ICT', 'code' => 'ICT']);
        Project::factory()->delayed()->create(['title' => 'Late project', 'department_id' => $ict->id]);
        Project::factory()->create(['title' => 'Planned project', 'status' => 'planned']);

        $this->actingAs($pm)->get(route('projects.index', ['delayed' => 1]))
            ->assertSee('Late project')->assertDontSee('Planned project');
        $this->actingAs($pm)->get(route('projects.index', ['status' => 'planned']))
            ->assertSee('Planned project')->assertDontSee('Late project');
        $this->actingAs($pm)->get(route('projects.index', ['department' => $ict->id]))
            ->assertSee('Late project')->assertDontSee('Planned project');
        $this->actingAs($pm)->get(route('projects.index', ['q' => 'Late']))
            ->assertSee('Late project')->assertDontSee('Planned project');
    }

    public function test_show_displays_delay_task_completion_and_budget(): void
    {
        $pm = $this->userWithRole('Project Manager');
        $project = Project::factory()->delayed()->create(['planned_budget' => 1000, 'currency' => 'USD', 'progress' => 35]);
        Task::factory()->completed()->create(['project_id' => $project->id]);
        Task::factory()->create(['project_id' => $project->id]);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'cancelled']);

        $this->assertSame(50, $project->taskCompletion());

        $this->actingAs($pm)->get(route('projects.show', $project))->assertOk()
            ->assertSee(__('Delayed'))
            ->assertSee('50%')
            ->assertSee('35%')
            ->assertSee('USD 1,000.00');
    }

    public function test_project_tasks_tab_only_lists_tasks_visible_to_the_user(): void
    {
        $officer = $this->userWithRole('Project Officer');
        $project = Project::factory()->create();
        Task::factory()->create(['project_id' => $project->id, 'title' => 'Officer own task', 'assigned_to' => $officer->id]);
        Task::factory()->create(['project_id' => $project->id, 'title' => 'Hidden colleague task']);

        $this->actingAs($officer)->get(route('projects.show', [$project, 'tab' => 'tasks']))->assertOk()
            ->assertSee('Officer own task')
            ->assertDontSee('Hidden colleague task')
            ->assertSee(route('tasks.create', ['project_id' => $project->id]), false);
    }
}
