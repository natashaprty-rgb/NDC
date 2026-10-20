<?php

namespace Tests\Feature\Tasks;

use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TaskManagementTest extends TestCase
{
    use RefreshDatabase;

    private function department(string $code): Department
    {
        return Department::query()->create(['name' => $code.' Department', 'code' => $code]);
    }

    public function test_every_task_page_renders(): void
    {
        $admin = $this->superAdmin();
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id, 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('tasks.index'))->assertOk()->assertSee($task->title);
        $this->actingAs($admin)->get(route('tasks.create'))->assertOk();
        $this->actingAs($admin)->get(route('tasks.create', ['project_id' => $project->id]))->assertOk()
            ->assertSee('value="'.$project->id.'" selected', false);
        $this->actingAs($admin)->get(route('tasks.show', $task))->assertOk();
        $this->actingAs($admin)->get(route('tasks.edit', $task))->assertOk();

        foreach (['pending', 'in_progress', 'submitted', 'completed', 'rejected', 'cancelled'] as $status) {
            $task->forceFill(['status' => $status, 'review_comment' => 'Note'])->saveQuietly();
            $this->actingAs($admin)->get(route('tasks.show', $task))->assertOk();
        }
    }

    public function test_general_staff_task_page_renders(): void
    {
        $staff = $this->userWithRole('General Staff', ['locale' => 'so']);
        $task = Task::factory()->create(['assigned_to' => $staff->id]);

        $this->actingAs($staff)->get(route('tasks.index'))->assertOk()->assertSee($task->title);
        $this->actingAs($staff)->get(route('tasks.show', $task))->assertOk()
            ->assertSee(route('tasks.start', $task), false)
            ->assertDontSee(route('tasks.edit', $task), false)
            ->assertDontSee(route('tasks.reassign', $task), false);
    }

    public function test_task_can_be_created_and_assignee_is_notified(): void
    {
        Notification::fake();
        $pm = $this->userWithRole('Project Manager');
        $assignee = User::factory()->create();
        $project = Project::factory()->create();
        $milestone = ProjectMilestone::factory()->for($project)->create();

        $this->actingAs($pm)->post(route('tasks.store'), [
            'title' => 'Prepare data migration plan',
            'description' => 'Plan the migration.',
            'expected_deliverable' => 'Migration plan document',
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'assigned_to' => $assignee->id,
            'priority' => 'high',
            'start_date' => '2026-10-20',
            'due_date' => '2026-11-15',
            'requires_review' => '1',
        ])->assertRedirect();

        $task = Task::query()->firstOrFail();
        $this->assertSame('pending', $task->status);
        $this->assertSame($pm->id, $task->created_by);
        $this->assertSame($assignee->id, $task->assigned_to);
        $this->assertTrue($task->requires_review);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'task', 'auditable_id' => $task->id]);
        Notification::assertSentTo($assignee, GenericNotification::class, fn ($n) => $n->kind === 'task_assigned'
            && $n->link === route('tasks.show', $task));
        Notification::assertNotSentTo($pm, GenericNotification::class);
    }

    public function test_task_assigned_to_department_notifies_department_head(): void
    {
        Notification::fake();
        $pm = $this->userWithRole('Project Manager');
        $head = User::factory()->create();
        $department = $this->department('FIN');
        $department->update(['head_user_id' => $head->id]);

        $this->actingAs($pm)->post(route('tasks.store'), [
            'title' => 'Department task', 'department_id' => $department->id, 'priority' => 'medium',
        ])->assertRedirect();

        $this->assertNull(Task::query()->firstOrFail()->assigned_to);
        Notification::assertSentTo($head, GenericNotification::class, fn ($n) => $n->kind === 'task_assigned');
    }

    public function test_task_validation(): void
    {
        $pm = $this->userWithRole('Project Manager');
        $project = Project::factory()->create();
        $foreignMilestone = ProjectMilestone::factory()->create();

        $this->actingAs($pm)->post(route('tasks.store'), [
            'title' => '',
            'priority' => 'whenever',
            'project_id' => $project->id,
            'milestone_id' => $foreignMilestone->id,
            'start_date' => '2026-10-20',
            'due_date' => '2026-10-01',
        ])->assertSessionHasErrors(['title', 'priority', 'milestone_id', 'due_date', 'assigned_to', 'department_id']);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_tasks_cannot_be_linked_to_archived_projects(): void
    {
        $pm = $this->userWithRole('Project Manager');
        $project = Project::factory()->archived()->create();

        $this->actingAs($pm)->post(route('tasks.store'), [
            'title' => 'X', 'assigned_to' => $pm->id, 'priority' => 'low', 'project_id' => $project->id,
        ])->assertSessionHasErrors('project_id');
    }

    public function test_task_can_be_edited_without_changing_assignment(): void
    {
        $pm = $this->userWithRole('Project Manager');
        $assignee = User::factory()->create();
        $task = Task::factory()->create(['assigned_to' => $assignee->id, 'created_by' => $pm->id]);

        $this->actingAs($pm)->put(route('tasks.update', $task), [
            'title' => 'Updated title', 'priority' => 'urgent', 'assigned_to' => $pm->id,
        ])->assertRedirect(route('tasks.show', $task));

        $task->refresh();
        $this->assertSame('Updated title', $task->title);
        $this->assertSame('urgent', $task->priority);
        $this->assertFalse($task->requires_review);
        $this->assertSame($assignee->id, $task->assigned_to);
    }

    public function test_general_staff_cannot_create_edit_delete_or_reassign(): void
    {
        $staff = $this->userWithRole('General Staff');
        $task = Task::factory()->create(['assigned_to' => $staff->id]);

        $this->actingAs($staff)->get(route('tasks.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('tasks.store'), ['title' => 'X', 'assigned_to' => $staff->id, 'priority' => 'low'])->assertForbidden();
        $this->actingAs($staff)->get(route('tasks.edit', $task))->assertForbidden();
        $this->actingAs($staff)->put(route('tasks.update', $task), ['title' => 'X', 'priority' => 'low'])->assertForbidden();
        $this->actingAs($staff)->delete(route('tasks.destroy', $task))->assertForbidden();
        $this->actingAs($staff)->post(route('tasks.reassign', $task), ['assigned_to' => User::factory()->create()->id])->assertForbidden();
        $this->actingAs($staff)->post(route('tasks.cancel', $task))->assertForbidden();
        $this->assertSame($staff->id, $task->fresh()->assigned_to);
    }

    public function test_director_can_delete_task(): void
    {
        $director = $this->userWithRole('Director or Management');
        $task = Task::factory()->create();

        $this->actingAs($director)->delete(route('tasks.destroy', $task))->assertRedirect(route('tasks.index'));
        $this->assertSoftDeleted($task);
    }

    public function test_users_only_see_their_own_created_or_department_tasks(): void
    {
        $ict = $this->department('ICT');
        $hr = $this->department('HR');
        $userA = $this->userWithRole('General Staff', ['department_id' => $ict->id]);
        $userB = $this->userWithRole('General Staff', ['department_id' => $hr->id]);
        $colleague = User::factory()->create(['department_id' => $ict->id]);

        $own = Task::factory()->create(['title' => 'Task of A', 'assigned_to' => $userA->id]);
        $created = Task::factory()->create(['title' => 'Created by A', 'assigned_to' => $colleague->id, 'created_by' => $userA->id]);
        $departmental = Task::factory()->create(['title' => 'ICT department work', 'assigned_to' => null, 'department_id' => $ict->id]);
        $foreign = Task::factory()->create(['title' => 'Task of B', 'assigned_to' => $userB->id, 'department_id' => $hr->id]);

        $this->actingAs($userA)->get(route('tasks.index'))->assertOk()
            ->assertSee('Task of A')->assertSee('Created by A')->assertSee('ICT department work')
            ->assertDontSee('Task of B');

        $this->actingAs($userA)->get(route('tasks.show', $own))->assertOk();
        $this->actingAs($userA)->get(route('tasks.show', $created))->assertOk();
        $this->actingAs($userA)->get(route('tasks.show', $departmental))->assertOk();
        $this->actingAs($userA)->get(route('tasks.show', $foreign))->assertForbidden();
        $this->actingAs($userA)->post(route('tasks.comments.store', $foreign), ['body' => 'Hi'])->assertForbidden();
        $this->actingAs($userA)->post(route('tasks.start', $foreign))->assertForbidden();

        $this->actingAs($userB)->get(route('tasks.show', $own))->assertForbidden();
        $this->actingAs($userB)->get(route('tasks.index'))->assertSee('Task of B')->assertDontSee('Task of A');

        // Filtering by another user's id does not widen visibility.
        $this->actingAs($userB)->get(route('tasks.index', ['assignee' => $userA->id]))->assertDontSee('Task of A');
    }

    public function test_view_all_permission_sees_every_task(): void
    {
        $auditor = $this->userWithRole('Read-Only Auditor');
        $task = Task::factory()->create(['title' => 'Somebody else task']);

        $this->actingAs($auditor)->get(route('tasks.index'))->assertSee('Somebody else task');
        $this->actingAs($auditor)->get(route('tasks.show', $task))->assertOk();
        // Viewing does not grant working on the task.
        $this->actingAs($auditor)->post(route('tasks.start', $task))->assertForbidden();
    }

    public function test_index_filters(): void
    {
        $pm = $this->userWithRole('Project Manager');
        $project = Project::factory()->create();
        Task::factory()->overdue()->create(['title' => 'Overdue item']);
        Task::factory()->create(['title' => 'Urgent item', 'priority' => 'urgent', 'project_id' => $project->id]);
        Task::factory()->completed()->create(['title' => 'Finished item', 'due_date' => now()->subMonth()]);
        Task::factory()->create(['title' => 'My item', 'assigned_to' => $pm->id]);

        $this->actingAs($pm)->get(route('tasks.index', ['overdue' => 1]))
            ->assertSee('Overdue item')->assertDontSee('Finished item')->assertDontSee('Urgent item');
        $this->actingAs($pm)->get(route('tasks.index', ['priority' => 'urgent']))
            ->assertSee('Urgent item')->assertDontSee('Overdue item');
        $this->actingAs($pm)->get(route('tasks.index', ['status' => 'completed']))
            ->assertSee('Finished item')->assertDontSee('Urgent item');
        $this->actingAs($pm)->get(route('tasks.index', ['status' => 'open']))
            ->assertSee('Urgent item')->assertDontSee('Finished item');
        $this->actingAs($pm)->get(route('tasks.index', ['project' => $project->id]))
            ->assertSee('Urgent item')->assertDontSee('My item');
        $this->actingAs($pm)->get(route('tasks.index', ['mine' => 1]))
            ->assertSee('My item')->assertDontSee('Urgent item');
        $this->actingAs($pm)->get(route('tasks.index', ['q' => 'Urgent']))
            ->assertSee('Urgent item')->assertDontSee('My item');
    }
}
