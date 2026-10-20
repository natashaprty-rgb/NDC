<?php

namespace Tests\Feature\Tasks;

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;

    private User $assignee;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->creator = $this->userWithRole('Project Manager');
        $this->assignee = $this->userWithRole('General Staff');
    }

    private function task(array $attributes = []): Task
    {
        return Task::factory()->create(['assigned_to' => $this->assignee->id, 'created_by' => $this->creator->id, ...$attributes]);
    }

    public function test_full_review_workflow(): void
    {
        $task = $this->task();

        $this->actingAs($this->assignee)->post(route('tasks.start', $task))->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);

        $this->actingAs($this->assignee)->patch(route('tasks.progress', $task), ['progress' => 80])->assertRedirect();
        $this->assertSame(80, $task->fresh()->progress);

        $this->actingAs($this->assignee)->post(route('tasks.hand-in', $task), ['note' => 'Ready'])->assertRedirect();
        $task->refresh();
        $this->assertSame('submitted', $task->status);
        $this->assertNotNull($task->submitted_at);
        $this->assertDatabaseHas('audit_logs', ['event' => 'submitted', 'auditable_type' => 'task', 'auditable_id' => $task->id]);
        Notification::assertSentTo($this->creator, GenericNotification::class, fn ($n) => $n->kind === 'task_submitted');

        $reviewer = $this->userWithRole('Department Head');
        $this->actingAs($reviewer)->get(route('tasks.show', $task))->assertForbidden(); // not visible to an unrelated reviewer

        $this->actingAs($this->creator)->get(route('tasks.show', $task))->assertOk()->assertSee(route('tasks.approve', $task), false);
        $this->actingAs($this->creator)->post(route('tasks.approve', $task), ['review_comment' => 'Good work'])->assertRedirect();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertNotNull($task->completed_at);
        $this->assertSame($this->creator->id, $task->reviewed_by);
        $this->assertNotNull($task->reviewed_at);
        $this->assertSame('Good work', $task->review_comment);
        $this->assertDatabaseHas('audit_logs', ['event' => 'approved', 'auditable_type' => 'task', 'auditable_id' => $task->id, 'user_id' => $this->creator->id]);
        Notification::assertSentTo($this->assignee, GenericNotification::class, fn ($n) => $n->kind === 'task_approved');

        $this->actingAs($this->creator)->get(route('tasks.show', $task))->assertOk()->assertSee(__('Approved'));
    }

    public function test_rejection_requires_comment_and_assignee_can_resume(): void
    {
        $task = $this->task(['status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs($this->creator)->post(route('tasks.reject', $task), ['review_comment' => ''])
            ->assertSessionHasErrors('review_comment');
        $this->assertSame('submitted', $task->fresh()->status);

        $this->actingAs($this->creator)->post(route('tasks.reject', $task), ['review_comment' => 'Missing annex'])->assertRedirect();
        $task->refresh();
        $this->assertSame('rejected', $task->status);
        $this->assertSame('Missing annex', $task->review_comment);
        $this->assertDatabaseHas('audit_logs', ['event' => 'rejected', 'auditable_type' => 'task', 'auditable_id' => $task->id]);
        Notification::assertSentTo($this->assignee, GenericNotification::class, fn ($n) => $n->kind === 'task_rejected'
            && $n->replace['reason'] === 'Missing annex');

        $this->actingAs($this->assignee)->get(route('tasks.show', $task))->assertOk()->assertSee('Missing annex');
        $this->actingAs($this->assignee)->post(route('tasks.resume', $task))->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);
    }

    public function test_assignee_cannot_approve_own_task_even_with_review_permission(): void
    {
        $reviewerAssignee = $this->userWithRole('Project Manager');
        $task = $this->task(['assigned_to' => $reviewerAssignee->id, 'status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs($reviewerAssignee)->post(route('tasks.approve', $task))->assertForbidden();
        $this->actingAs($reviewerAssignee)->post(route('tasks.reject', $task), ['review_comment' => 'x'])->assertForbidden();
        $this->assertSame('submitted', $task->fresh()->status);

        $this->actingAs($reviewerAssignee)->get(route('tasks.show', $task))->assertOk()
            ->assertDontSee(route('tasks.approve', $task), false);
    }

    public function test_super_admin_assignee_cannot_approve_own_task(): void
    {
        $admin = $this->superAdmin();
        $task = $this->task(['assigned_to' => $admin->id, 'status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs($admin)->post(route('tasks.approve', $task))->assertForbidden();
        $this->assertSame('submitted', $task->fresh()->status);
    }

    public function test_general_staff_cannot_review(): void
    {
        $colleague = $this->userWithRole('General Staff');
        $task = $this->task(['status' => 'submitted', 'created_by' => $colleague->id]);

        $this->actingAs($colleague)->post(route('tasks.approve', $task))->assertForbidden();
    }

    public function test_task_without_review_is_completed_directly(): void
    {
        $task = $this->task(['status' => 'in_progress', 'requires_review' => false]);

        $this->actingAs($this->assignee)->post(route('tasks.hand-in', $task))->assertRedirect();

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertSame(100, $task->progress);
        $this->assertNotNull($task->completed_at);
        Notification::assertSentTo($this->creator, GenericNotification::class, fn ($n) => $n->kind === 'task_completed');
    }

    public function test_invalid_transitions_are_refused(): void
    {
        $task = $this->task(['status' => 'pending']);

        // Cannot submit before starting, cannot resume a task that was not returned.
        $this->actingAs($this->assignee)->post(route('tasks.hand-in', $task))->assertForbidden();
        $this->actingAs($this->assignee)->post(route('tasks.resume', $task))->assertForbidden();
        $this->actingAs($this->creator)->post(route('tasks.approve', $task))->assertForbidden();

        // Status rules also apply to Super Administrators.
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('tasks.approve', $task))->assertForbidden();
        $this->actingAs($admin)->post(route('tasks.hand-in', $task))->assertForbidden();
        $this->assertSame('pending', $task->fresh()->status);

        $completed = $this->task(['status' => 'completed', 'progress' => 100]);
        $this->actingAs($admin)->patch(route('tasks.progress', $completed), ['progress' => 10])->assertForbidden();
        $this->assertSame(100, $completed->fresh()->progress);
    }

    public function test_progress_validation_and_auto_start(): void
    {
        $task = $this->task(['status' => 'pending']);

        $this->actingAs($this->assignee)->patch(route('tasks.progress', $task), ['progress' => 120])->assertSessionHasErrors('progress');
        $this->actingAs($this->assignee)->patch(route('tasks.progress', $task), ['progress' => 25])->assertRedirect();

        $task->refresh();
        $this->assertSame(25, $task->progress);
        $this->assertSame('in_progress', $task->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'progress_updated', 'auditable_type' => 'task', 'auditable_id' => $task->id]);
    }

    public function test_other_staff_cannot_update_progress_of_a_department_task(): void
    {
        $department = Department::query()->create(['name' => 'ICT', 'code' => 'ICT']);
        $colleague = $this->userWithRole('General Staff', ['department_id' => $department->id]);
        $task = $this->task(['department_id' => $department->id]);

        $this->actingAs($colleague)->get(route('tasks.show', $task))->assertOk();
        $this->actingAs($colleague)->patch(route('tasks.progress', $task), ['progress' => 50])->assertForbidden();
        $this->actingAs($colleague)->post(route('tasks.start', $task))->assertForbidden();
    }

    public function test_reassignment_notifies_new_assignee_and_is_audited(): void
    {
        $task = $this->task(['status' => 'in_progress']);
        $newAssignee = User::factory()->create();

        $this->actingAs($this->creator)->post(route('tasks.reassign', $task), [
            'assigned_to' => $newAssignee->id, 'note' => 'Workload balancing',
        ])->assertRedirect();

        $this->assertSame($newAssignee->id, $task->fresh()->assigned_to);
        $this->assertDatabaseHas('audit_logs', ['event' => 'reassigned', 'auditable_type' => 'task', 'auditable_id' => $task->id, 'description' => 'Workload balancing']);
        Notification::assertSentTo($newAssignee, GenericNotification::class, fn ($n) => $n->kind === 'task_reassigned');

        // The previous assignee can no longer see it.
        $this->actingAs($this->assignee)->get(route('tasks.show', $task))->assertForbidden();

        // Reassigning to the same person is refused.
        $this->actingAs($this->creator)->post(route('tasks.reassign', $task), ['assigned_to' => $newAssignee->id])
            ->assertSessionHasErrors('assigned_to');
        $this->actingAs($this->creator)->post(route('tasks.reassign', $task), [])->assertSessionHasErrors(['assigned_to', 'department_id']);
    }

    public function test_cancel_and_reopen(): void
    {
        $task = $this->task(['status' => 'in_progress']);

        $this->actingAs($this->assignee)->post(route('tasks.cancel', $task))->assertForbidden();

        $this->actingAs($this->creator)->post(route('tasks.cancel', $task))->assertRedirect();
        $this->assertSame('cancelled', $task->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'cancelled', 'auditable_type' => 'task', 'auditable_id' => $task->id]);
        Notification::assertSentTo($this->assignee, GenericNotification::class, fn ($n) => $n->kind === 'task_cancelled');

        $this->actingAs($this->assignee)->post(route('tasks.start', $task))->assertForbidden();

        $this->actingAs($this->creator)->post(route('tasks.reopen', $task))->assertRedirect();
        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'reopened', 'auditable_type' => 'task', 'auditable_id' => $task->id]);
    }

    public function test_assignee_can_comment_with_completion_evidence(): void
    {
        Storage::fake('local');
        $task = $this->task(['status' => 'in_progress']);

        $this->actingAs($this->assignee)->post(route('tasks.comments.store', $task), [
            'body' => 'Report attached.',
            'is_evidence' => '1',
            'file' => UploadedFile::fake()->create('final-report.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $comment = $task->comments()->firstOrFail();
        $this->assertTrue($comment->is_evidence);
        $this->assertSame($this->assignee->id, $comment->user_id);
        $attachment = $comment->attachments()->firstOrFail();
        $this->assertSame('evidence', $attachment->collection);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('audit_logs', ['event' => 'evidence_added', 'auditable_type' => 'task', 'auditable_id' => $task->id]);
        Notification::assertSentTo($this->creator, GenericNotification::class, fn ($n) => $n->kind === 'task_comment');

        $this->actingAs($this->creator)->get(route('tasks.show', $task))->assertOk()
            ->assertSee('Report attached.')->assertSee('final-report.pdf');
        $this->actingAs($this->creator)->get(route('attachments.download', $attachment))->assertOk();

        $outsider = $this->userWithRole('General Staff');
        $this->actingAs($outsider)->get(route('attachments.download', $attachment))->assertForbidden();
    }

    public function test_department_colleague_may_comment_but_not_attach_evidence(): void
    {
        Storage::fake('local');
        $department = Department::query()->create(['name' => 'ICT', 'code' => 'ICT']);
        $colleague = $this->userWithRole('General Staff', ['department_id' => $department->id]);
        $task = $this->task(['department_id' => $department->id]);

        $this->actingAs($colleague)->post(route('tasks.comments.store', $task), ['body' => 'Happy to help.'])->assertRedirect();
        $this->assertSame(1, $task->comments()->count());

        $this->actingAs($colleague)->post(route('tasks.comments.store', $task), [
            'body' => 'Evidence', 'is_evidence' => '1', 'file' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf'),
        ])->assertForbidden();
        $this->assertSame(1, $task->comments()->count());

        $this->actingAs($colleague)->post(route('tasks.comments.store', $task), ['body' => ''])->assertSessionHasErrors('body');
    }

    public function test_assignee_can_upload_task_attachment(): void
    {
        Storage::fake('local');
        $task = $this->task(['status' => 'in_progress']);

        $this->actingAs($this->assignee)->post(route('attachments.store'), [
            'attachable_type' => 'task', 'attachable_id' => $task->id,
            'file' => UploadedFile::fake()->create('draft.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])->assertRedirect();

        $this->assertSame(1, $task->attachments()->count());
    }
}
