<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Support\Audit;
use App\Support\FileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Comments on a task. People working on the task (the assignee or holders of
 * tasks.update) may also attach a file and mark the comment as completion
 * evidence.
 */
class TaskCommentController extends Controller
{
    public function __construct(private TaskNotifier $notifier) {}

    public function store(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('comment', $task);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_evidence' => ['boolean'],
            'file' => FileStore::rules(),
        ]);

        $evidence = $request->boolean('is_evidence');
        if (($evidence || $request->hasFile('file')) && ! $request->user()->can('update', $task)) {
            $this->authorize('update', $task);
        }

        DB::transaction(function () use ($request, $task, $data, $evidence) {
            $comment = $task->comments()->create([
                'user_id' => $request->user()->id,
                'body' => $data['body'],
                'is_evidence' => $evidence,
            ]);

            if ($request->hasFile('file')) {
                FileStore::attach($comment, $request->file('file'), $evidence ? 'evidence' : 'default');
            }

            Audit::log($evidence ? 'evidence_added' : 'commented', $task, Str::limit($data['body'], 200));
        });

        $this->notifier->commented($task, $request->user());

        return redirect()->to(route('tasks.show', $task).'#comments')
            ->with('success', $evidence ? __('Completion evidence added.') : __('Comment added.'));
    }
}
