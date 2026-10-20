<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Support\Audit;
use App\Support\Status;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Status workflow of a task:
 *
 *   pending → in_progress → submitted → completed   (requires_review)
 *                         ↘ completed                (no review needed)
 *   submitted → rejected → in_progress → …          (returned for rework)
 *   any open status → cancelled → reopened (in_progress)
 *   completed → reopened (in_progress)
 *
 * The assignee (or a holder of tasks.update) moves the work forward; a
 * reviewer with tasks.review who is not the assignee approves or rejects it.
 * Every step is written to the audit trail with the old and new values.
 */
class TaskWorkflowController extends Controller
{
    public function __construct(private TaskNotifier $notifier) {}

    public function start(Task $task): RedirectResponse
    {
        $this->authorize('start', $task);
        $this->ensure($task, $task->canStart());

        $this->transition($task, ['status' => 'in_progress'], 'started');

        return back()->with('success', __('Work on the task has started.'));
    }

    /**
     * Submit the work for review, or complete it directly when the task
     * does not require review.
     */
    public function handIn(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('handIn', $task);
        $this->ensure($task, $task->canHandIn());
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        if ($task->requires_review) {
            $this->transition($task, ['status' => 'submitted', 'submitted_at' => now()], 'submitted', $data['note'] ?? null);
            $message = __('Task submitted for review.');
        } else {
            $this->transition($task, ['status' => 'completed', 'completed_at' => now(), 'progress' => 100], 'completed', $data['note'] ?? null);
            $message = __('Task marked as completed.');
        }

        $this->notifier->handedIn($task, $request->user());

        return back()->with('success', $message);
    }

    public function resume(Task $task): RedirectResponse
    {
        $this->authorize('resume', $task);
        $this->ensure($task, $task->canResume());

        $this->transition($task, ['status' => 'in_progress'], 'resumed');

        return back()->with('success', __('Work on the task has resumed.'));
    }

    public function progress(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('progress', $task);
        $this->ensure($task, $task->acceptsProgress());
        $data = $request->validate(['progress' => ['required', 'integer', 'min:0', 'max:100']]);

        $changes = ['progress' => $data['progress']];
        if ($task->status === 'pending' && $data['progress'] > 0) {
            $changes['status'] = 'in_progress';
        }

        $this->transition($task, $changes, 'progress_updated', $data['progress'].'%');

        return back()->with('success', __('Progress updated.'));
    }

    public function approve(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeReview($request, $task);
        $data = $request->validate(['review_comment' => ['nullable', 'string', 'max:5000']]);

        $this->transition($task, [
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_comment' => $data['review_comment'] ?? null,
        ], 'approved', $data['review_comment'] ?? null);

        $this->notifier->reviewed($task, $request->user());

        return back()->with('success', __('Task approved and completed.'));
    }

    public function reject(Request $request, Task $task): RedirectResponse
    {
        $this->authorizeReview($request, $task);
        $data = $request->validate(['review_comment' => ['required', 'string', 'max:5000']], [
            'review_comment.required' => __('A reason is required when returning a task.'),
        ]);

        $this->transition($task, [
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_comment' => $data['review_comment'],
        ], 'rejected', $data['review_comment']);

        $this->notifier->reviewed($task, $request->user());

        return back()->with('success', __('Task returned to the assignee for rework.'));
    }

    public function reassign(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('reassign', $task);
        $this->ensure($task, $task->isOpen());

        $data = $request->validate([
            'assigned_to' => ['nullable', 'required_without:department_id', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'department_id' => ['nullable', 'required_without:assigned_to', 'integer', Rule::exists('departments', 'id')],
            'note' => ['nullable', 'string', 'max:2000'],
        ], [
            'assigned_to.required_without' => __('Assign the task to a staff member, a department or both.'),
            'department_id.required_without' => __('Assign the task to a staff member, a department or both.'),
        ]);

        $changes = ['assigned_to' => $data['assigned_to'] ?? null, 'department_id' => $data['department_id'] ?? null];
        if ((int) $task->assigned_to === (int) $changes['assigned_to'] && (int) $task->department_id === (int) $changes['department_id']) {
            throw ValidationException::withMessages(['assigned_to' => __('Choose a different assignee or department.')]);
        }

        $previousAssignee = $task->assigned_to;
        $this->transition($task, $changes, 'reassigned', $data['note'] ?? null);

        if ((int) $previousAssignee !== (int) $task->assigned_to || $task->assigned_to === null) {
            $this->notifier->reassigned($task, $request->user());
        }

        return back()->with('success', __('Task reassigned.'));
    }

    public function cancel(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('cancel', $task);
        $this->ensure($task, $task->isOpen());
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->transition($task, ['status' => 'cancelled'], 'cancelled', $data['note'] ?? null);
        $this->notifier->cancelled($task, $request->user());

        return back()->with('success', __('Task cancelled.'));
    }

    public function reopen(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('reopen', $task);
        $this->ensure($task, $task->canReopen());

        $this->transition($task, ['status' => 'in_progress', 'completed_at' => null], 'reopened');
        $this->notifier->reopened($task, $request->user());

        return back()->with('success', __('Task reopened.'));
    }

    /**
     * Reviewers need tasks.review and may never review their own work. The
     * separation of duties is checked here as well as in the policy so it
     * holds for every role, including Super Administrators.
     */
    private function authorizeReview(Request $request, Task $task): void
    {
        $this->authorize('review', $task);

        if ($task->isAssignedTo($request->user())) {
            throw new AuthorizationException(__('You cannot review a task assigned to you.'));
        }

        $this->ensure($task, $task->isAwaitingReview());
    }

    private function ensure(Task $task, bool $allowed): void
    {
        if (! $allowed) {
            throw ValidationException::withMessages([
                'status' => __('This action is not available while the task status is ":status".', [
                    'status' => Status::label('task', $task->status),
                ]),
            ]);
        }
    }

    /**
     * Apply a workflow change and record it as one audit entry carrying the
     * old and new values.
     *
     * @param  array<string, mixed>  $changes
     */
    private function transition(Task $task, array $changes, string $event, ?string $note = null): void
    {
        $old = array_map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value,
            array_intersect_key($task->getAttributes(), $changes));

        $task->forceFill($changes)->saveQuietly();

        $new = array_map(fn ($value) => $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value, $changes);
        Audit::log($event, $task, $note ?? $task->title, $old, $new);
    }
}
