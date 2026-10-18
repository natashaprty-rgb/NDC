<?php

namespace Tests\Feature\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    public function test_creating_task_assigns_reference_and_notifies_assignee(): void
    {
        $pm = $this->userWithRole('project-manager');
        $officer = $this->userWithRole('project-officer');
        $project = Project::factory()->create(['manager_id' => $pm->id]);

        $this->actingAs($pm)->post('/tasks', [
            'title' => 'Prepare site survey', 'project_id' => $project->id, 'priority' => 'high',
            'due_date' => now()->addWeek()->toDateString(), 'assigned_to' => $officer->id,
        ])->assertRedirect();

        $task = Task::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^NDC-TSK-\d{4}-\d{4}$/', $task->reference);
        $this->assertSame($pm->id, $task->assigned_by);
        $this->assertSame('todo', $task->status);

        $notification = $officer->notifications()->first();
        $this->assertNotNull($notification);
        $this->assertSame('task_assigned', $notification->data['kind']);
        $this->assertSame('tasks.notify.assigned', $notification->data['title_key']);
        $this->assertSame(0, $pm->notifications()->count(), 'The actor is not notified');
    }

    public function test_milestone_from_another_project_is_rejected(): void
    {
        $pm = $this->userWithRole('project-manager');
        $project = Project::factory()->create(['manager_id' => $pm->id]);
        $foreign = ProjectMilestone::factory()->create();

        $this->actingAs($pm)->post('/tasks', ['title' => 'T', 'priority' => 'low', 'project_id' => $project->id, 'milestone_id' => $foreign->id])
            ->assertSessionHasErrors('milestone_id');
        $this->actingAs($pm)->post('/tasks', ['title' => 'T', 'priority' => 'low', 'milestone_id' => $foreign->id])
            ->assertSessionHasErrors('milestone_id');
        $this->assertSame(0, Task::query()->count());
    }

    public function test_tasks_cannot_be_added_to_archived_projects_and_approver_differs_from_assignee(): void
    {
        $pm = $this->userWithRole('project-manager');
        $officer = $this->userWithRole('project-officer');
        $archived = Project::factory()->create(['manager_id' => $pm->id, 'archived_at' => now()]);

        $this->actingAs($pm)->post('/tasks', ['title' => 'T', 'priority' => 'low', 'project_id' => $archived->id])->assertSessionHasErrors('project_id');
        $this->actingAs($pm)->post('/tasks', ['title' => 'T', 'priority' => 'low', 'assigned_to' => $officer->id, 'requires_approval' => '1', 'approver_id' => $officer->id])
            ->assertSessionHasErrors('approver_id');
    }

    public function test_reassignment_is_audited_and_notifies_new_assignee(): void
    {
        $pm = $this->userWithRole('project-manager');
        $first = $this->userWithRole('project-officer');
        $second = $this->userWithRole('project-officer');
        $this->actingAs($pm);
        $task = Task::factory()->create(['assigned_to' => $first->id, 'assigned_by' => $pm->id]);

        $this->put("/tasks/{$task->id}", ['title' => $task->title, 'priority' => 'medium', 'assigned_to' => $second->id])->assertRedirect();

        $this->assertSame($second->id, $task->fresh()->assigned_to);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'task', 'auditable_id' => $task->id, 'event' => 'reassigned']);
        $this->assertSame(1, $second->notifications()->count());
    }

    public function test_assignee_cannot_edit_or_reassign_task_they_did_not_create(): void
    {
        $pm = $this->userWithRole('project-manager');
        $officer = $this->userWithRole('project-officer');
        $task = Task::factory()->create(['assigned_to' => $officer->id, 'created_by' => $pm->id, 'assigned_by' => $pm->id]);

        $this->actingAs($officer)->put("/tasks/{$task->id}", ['title' => 'Mine now', 'priority' => 'low', 'assigned_to' => $officer->id])->assertForbidden();
        $this->assertNotSame('Mine now', $task->fresh()->title);
    }

    public function test_assignee_updates_progress_and_completes_task_without_approval(): void
    {
        $officer = $this->userWithRole('project-officer');
        $other = $this->userWithRole('project-officer');
        $task = Task::factory()->create(['assigned_to' => $officer->id, 'requires_approval' => false]);

        $this->actingAs($other)->post("/tasks/{$task->id}/progress", ['progress' => 50, 'status' => 'in_progress'])->assertForbidden();

        $this->actingAs($officer)->post("/tasks/{$task->id}/progress", ['progress' => 150, 'status' => 'in_progress'])->assertSessionHasErrors('progress');
        $this->actingAs($officer)->post("/tasks/{$task->id}/progress", ['progress' => 40, 'status' => 'in_progress'])->assertRedirect();
        $this->assertSame(40, $task->fresh()->progress);

        $this->actingAs($officer)->post("/tasks/{$task->id}/submit", [])->assertSessionHasErrors('completion_evidence');
        $this->actingAs($officer)->post("/tasks/{$task->id}/submit", ['completion_evidence' => 'Report filed in registry'])->assertRedirect();

        $fresh = $task->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame(100, $fresh->progress);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_submission_requiring_approval_notifies_approver_and_stores_evidence_file(): void
    {
        Storage::fake('local');
        $pm = $this->userWithRole('project-manager');
        $officer = $this->userWithRole('project-officer');
        $task = Task::factory()->create(['assigned_to' => $officer->id, 'created_by' => $pm->id, 'requires_approval' => true, 'approver_id' => $pm->id]);

        $this->actingAs($officer)->post("/tasks/{$task->id}/submit", [
            'completion_evidence' => 'Survey complete',
            'evidence_file' => UploadedFile::fake()->createWithContent('survey.txt', 'survey results'),
        ])->assertRedirect();

        $fresh = $task->fresh();
        $this->assertSame('awaiting_approval', $fresh->status);
        $this->assertNotNull($fresh->submitted_at);
        $this->assertSame(1, $fresh->attachments()->where('category', 'evidence')->count());
        $this->assertSame('tasks.notify.submitted', $pm->notifications()->first()->data['title_key']);

        // Further progress updates are blocked while waiting for approval.
        $this->actingAs($officer)->post("/tasks/{$task->id}/progress", ['progress' => 10, 'status' => 'todo'])->assertForbidden();
    }

    public function test_assignee_cannot_approve_own_task_even_with_approve_permission(): void
    {
        $manager = $this->userWithRole('project-manager'); // holds tasks.approve
        $task = Task::factory()->create([
            'assigned_to' => $manager->id, 'created_by' => $manager->id, 'assigned_by' => $manager->id,
            'requires_approval' => true, 'status' => 'awaiting_approval', 'submitted_at' => now(),
        ]);

        $this->assertTrue($manager->can('tasks.approve'));
        $this->actingAs($manager)->post("/tasks/{$task->id}/approve")->assertForbidden();
        $this->assertSame('awaiting_approval', $task->fresh()->status);
    }

    public function test_designated_approver_approves_and_others_cannot(): void
    {
        $approver = $this->userWithRole('department-head');
        $creator = $this->userWithRole('project-manager');
        $officer = $this->userWithRole('project-officer');
        $task = Task::factory()->create([
            'assigned_to' => $officer->id, 'created_by' => $creator->id, 'approver_id' => $approver->id,
            'requires_approval' => true, 'status' => 'awaiting_approval', 'submitted_at' => now(),
        ]);

        // A designated approver exists, so the creator may not approve.
        $this->actingAs($creator)->post("/tasks/{$task->id}/approve")->assertForbidden();

        $this->actingAs($approver)->post("/tasks/{$task->id}/approve", ['review_note' => 'Good work'])->assertRedirect();
        $fresh = $task->fresh();
        $this->assertSame('completed', $fresh->status);
        $this->assertSame($approver->id, $fresh->approved_by);
        $this->assertNotNull($fresh->approved_at);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'task', 'auditable_id' => $task->id, 'event' => 'approved']);
        $this->assertSame('tasks.notify.approved', $officer->notifications()->first()->data['title_key']);
    }

    public function test_rejection_requires_note_and_returns_task_to_in_progress(): void
    {
        $pm = $this->userWithRole('project-manager');
        $officer = $this->userWithRole('project-officer');
        $task = Task::factory()->create([
            'assigned_to' => $officer->id, 'created_by' => $pm->id, 'requires_approval' => true,
            'status' => 'awaiting_approval', 'submitted_at' => now(),
        ]);

        $this->actingAs($pm)->post("/tasks/{$task->id}/reject", [])->assertSessionHasErrors('review_note');
        $this->actingAs($pm)->post("/tasks/{$task->id}/reject", ['review_note' => 'Missing annex B'])->assertRedirect();

        $fresh = $task->fresh();
        $this->assertSame('in_progress', $fresh->status);
        $this->assertSame('Missing annex B', $fresh->review_note);
        $this->assertSame('tasks.notify.rejected', $officer->notifications()->first()->data['title_key']);
    }

    public function test_cancel_comment_and_delete_rules(): void
    {
        $pm = $this->userWithRole('project-manager');
        $officer = $this->userWithRole('project-officer');
        $task = Task::factory()->create(['assigned_to' => $officer->id, 'created_by' => $pm->id]);

        $this->actingAs($officer)->post("/tasks/{$task->id}/comments", ['body' => 'Started today'])->assertRedirect();
        $this->assertDatabaseHas('task_comments', ['task_id' => $task->id, 'user_id' => $officer->id]);
        $this->assertSame('tasks.notify.comment', $pm->notifications()->first()->data['title_key']);

        $this->actingAs($officer)->post("/tasks/{$task->id}/cancel", ['reason' => 'No'])->assertForbidden();
        $this->actingAs($officer)->delete("/tasks/{$task->id}")->assertForbidden();

        $this->actingAs($pm)->post("/tasks/{$task->id}/cancel", ['reason' => 'Superseded'])->assertRedirect();
        $this->assertSame('cancelled', $task->fresh()->status);

        $todo = Task::factory()->create(['created_by' => $pm->id]);
        $this->actingAs($pm)->delete("/tasks/{$todo->id}")->assertRedirect();
        $this->assertSoftDeleted($todo);
    }
}
