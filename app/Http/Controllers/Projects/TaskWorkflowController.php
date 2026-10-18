<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Services\FileStorage;
use App\Services\Projects\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Assignee progress / submission, approval decisions, cancellation and comments. */
class TaskWorkflowController extends Controller
{
    public function __construct(private readonly TaskService $tasks) {}

    public function progress(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('updateProgress', $task);
        $data = $request->validate([
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'status' => ['required', Rule::in(['todo', 'in_progress'])],
        ], [], ['progress' => __('common.progress'), 'status' => __('common.status')]);

        $this->tasks->updateProgress($task, (int) $data['progress'], $data['status']);

        return back()->with('success', __('common.saved'));
    }

    public function submit(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('submit', $task);
        $request->validate([
            'completion_evidence' => ['required', 'string', 'max:5000'],
            'evidence_file' => FileStorage::rules(false),
        ], [], ['completion_evidence' => __('tasks.completion_evidence'), 'evidence_file' => __('tasks.evidence_file')]);

        $this->tasks->submit($task, (string) $request->input('completion_evidence'), $request->file('evidence_file'));

        return back()->with('success', $task->status === 'awaiting_approval' ? __('tasks.submitted_for_approval') : __('tasks.marked_complete'));
    }

    public function approve(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('approve', $task);
        $data = $request->validate(['review_note' => ['nullable', 'string', 'max:2000']]);
        $this->tasks->approve($task, $request->user(), $data['review_note'] ?? null);

        return back()->with('success', __('tasks.approved'));
    }

    public function reject(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('approve', $task);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']], [], ['review_note' => __('common.reason')]);
        $this->tasks->reject($task, $data['review_note']);

        return back()->with('success', __('tasks.rejected'));
    }

    public function cancel(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('cancel', $task);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], [], ['reason' => __('common.reason')]);
        $this->tasks->cancel($task, $data['reason']);

        return back()->with('success', __('tasks.cancelled'));
    }

    public function comment(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('comment', $task);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']], [], ['body' => __('common.comment')]);
        $this->tasks->comment($task, $request->user(), $data['body']);

        return redirect()->to(route('tasks.show', $task).'#comments')->with('success', __('tasks.comment_added'));
    }
}
