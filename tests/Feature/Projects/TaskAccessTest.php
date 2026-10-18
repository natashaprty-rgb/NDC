<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\Task;
use Tests\TestCase;

class TaskAccessTest extends TestCase
{
    public function test_non_participant_cannot_view_task_and_does_not_see_it_in_lists(): void
    {
        $owner = $this->userWithRole('project-manager');
        $outsider = $this->userWithRole('project-officer');
        $task = Task::factory()->create(['title' => 'Secret procurement prep', 'assigned_to' => $owner->id, 'created_by' => $owner->id]);

        $this->actingAs($outsider)->get("/tasks/{$task->id}")->assertForbidden();
        $this->actingAs($outsider)->post("/tasks/{$task->id}/comments", ['body' => 'x'])->assertForbidden();
        foreach (['mine', 'created', 'approvals', 'team'] as $scope) {
            $this->actingAs($outsider)->get("/tasks?scope={$scope}")->assertOk()->assertDontSee('Secret procurement prep');
        }
    }

    public function test_scope_all_requires_view_all_permission(): void
    {
        $officer = $this->userWithRole('project-officer');
        $director = $this->userWithRole('director');
        Task::factory()->create(['title' => 'Organisation-wide task']);

        $this->actingAs($officer)->get('/tasks?scope=all')->assertForbidden();
        $this->actingAs($director)->get('/tasks?scope=all')->assertOk()->assertSee('Organisation-wide task');
    }

    public function test_project_manager_can_view_tasks_of_own_project(): void
    {
        $pm = $this->userWithRole('project-manager');
        $project = Project::factory()->create(['manager_id' => $pm->id]);
        $task = Task::factory()->create(['project_id' => $project->id, 'title' => 'Under my project']);

        $this->actingAs($pm)->get("/tasks/{$task->id}")->assertOk()->assertSee('Under my project');
    }

    public function test_department_head_sees_team_tasks(): void
    {
        $head = $this->userWithRole('department-head');
        $member = $this->userWithRole('staff');
        $department = $this->placeInDepartment($member);
        $department->update(['head_user_id' => $head->id]);
        Task::factory()->create(['department_id' => $department->id, 'assigned_to' => $member->id, 'title' => 'Team task']);
        Task::factory()->create(['title' => 'Other department task']);

        $this->actingAs($head)->get('/tasks?scope=team')->assertOk()->assertSee('Team task')->assertDontSee('Other department task');
    }

    public function test_overdue_filter_returns_only_overdue_open_tasks(): void
    {
        $officer = $this->userWithRole('project-officer');
        Task::factory()->overdue()->create(['assigned_to' => $officer->id, 'title' => 'Late open task']);
        Task::factory()->create(['assigned_to' => $officer->id, 'title' => 'Future task', 'due_date' => today()->addDays(5)]);
        Task::factory()->create(['assigned_to' => $officer->id, 'title' => 'Late but completed', 'status' => 'completed', 'due_date' => today()->subDays(5)]);

        $this->actingAs($officer)->get('/tasks?scope=mine&overdue=1')
            ->assertOk()->assertSee('Late open task')->assertDontSee('Future task')->assertDontSee('Late but completed');

        $this->assertSame(1, Task::query()->overdue()->count());
    }

    public function test_status_open_filter_and_approvals_scope(): void
    {
        $pm = $this->userWithRole('project-manager');
        $officer = $this->userWithRole('project-officer');
        Task::factory()->create(['assigned_to' => $officer->id, 'created_by' => $pm->id, 'title' => 'Waiting task', 'status' => 'awaiting_approval', 'requires_approval' => true, 'submitted_at' => now()]);
        Task::factory()->create(['assigned_to' => $officer->id, 'created_by' => $pm->id, 'title' => 'Finished task', 'status' => 'completed']);

        $this->actingAs($officer)->get('/tasks?scope=mine&status=open')->assertSee('Waiting task')->assertDontSee('Finished task');
        $this->actingAs($pm)->get('/tasks?scope=approvals')->assertSee('Waiting task')->assertDontSee('Finished task');
        // The assignee never sees their own task in the approvals tab.
        $this->actingAs($officer)->get('/tasks?scope=approvals')->assertDontSee('Waiting task');
    }

    public function test_staff_without_tasks_create_cannot_create(): void
    {
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->get('/tasks')->assertOk();
        $this->actingAs($staff)->get('/tasks/create')->assertForbidden();
        $this->actingAs($staff)->post('/tasks', ['title' => 'X', 'priority' => 'low'])->assertForbidden();
    }
}
