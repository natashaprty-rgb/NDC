<?php

namespace App\Http\Controllers\Tasks;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private TaskNotifier $notifier) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Task::class);
        $user = $request->user();

        $query = Task::query()->visibleTo($user)->with(['assignee', 'department', 'project'])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%")))
            ->when($request->query('status'), fn (Builder $q, $s) => $s === 'open' ? $q->open() : $q->where('status', $s))
            ->when($request->query('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('assignee'), fn ($q, $a) => $q->where('assigned_to', $a))
            ->when($request->query('department'), fn ($q, $d) => $q->where('tasks.department_id', $d))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->boolean('overdue'), fn (Builder $q) => $q->overdue())
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $user->id));

        $this->applySort($query, $request, ['title', 'status', 'progress', 'due_date', 'created_at'], 'created_at');

        return view('tasks.index', [
            'tasks' => $query->paginate($this->perPage($request))->withQueryString(),
            'statuses' => ['open' => __('All open tasks')] + Status::options('task'),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id'),
            'departments' => Department::options(),
            'projects' => Project::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);

        $projectId = Project::query()->active()->whereKey($request->integer('project_id'))->value('id');

        return view('tasks.form', $this->formData(new Task([
            'project_id' => $projectId,
            'priority' => 'medium',
            'requires_review' => true,
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $task = Task::query()->create([
            ...$this->validated($request),
            'created_by' => $request->user()->id,
            'status' => 'pending',
            'progress' => 0,
        ]);

        $this->notifier->assigned($task, $request->user());

        return redirect()->route('tasks.show', $task)->with('success', __('Task created.'));
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('view', $task);

        $task->load([
            'project', 'milestone', 'assignee', 'department', 'creator', 'reviewer', 'attachments.uploader',
            'comments.user', 'comments.attachments',
        ]);

        $canReassign = $request->user()->can('reassign', $task);

        return view('tasks.show', [
            'task' => $task,
            'users' => $canReassign ? $this->userOptions($task->assigned_to) : [],
            'departments' => $canReassign ? Department::options() : [],
        ]);
    }

    public function edit(Task $task): View
    {
        $this->authorize('edit', $task);

        return view('tasks.form', $this->formData($task));
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('edit', $task);

        $task->update($this->validated($request, $task));

        return redirect()->route('tasks.show', $task)->with('success', __('Task updated.'));
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return redirect()->route('tasks.index')->with('success', __('Task deleted.'));
    }

    /**
     * The assignee and department are set when the task is created and
     * changed afterwards only through reassignment, which notifies people.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Task $task = null): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'expected_deliverable' => ['nullable', 'string', 'max:5000'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')
                ->where(fn ($q) => $q->whereNull('archived_at')->when($task?->project_id, fn ($q, $id) => $q->orWhere('id', $id)))],
            'milestone_id' => ['nullable', 'integer', Rule::exists('project_milestones', 'id')
                ->where('project_id', (int) $request->input('project_id'))],
            'priority' => ['required', Rule::in(Status::keys('priority'))],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'requires_review' => ['boolean'],
        ];

        if ($task === null) {
            $rules['assigned_to'] = ['nullable', 'required_without:department_id', 'integer', Rule::exists('users', 'id')->where('status', 'active')];
            $rules['department_id'] = ['nullable', 'required_without:assigned_to', 'integer', Rule::exists('departments', 'id')];
        }

        $data = $request->validate($rules, [
            'milestone_id.exists' => __('The selected milestone does not belong to the selected project.'),
            'assigned_to.required_without' => __('Assign the task to a staff member, a department or both.'),
            'department_id.required_without' => __('Assign the task to a staff member, a department or both.'),
        ]);
        $data['requires_review'] = $request->boolean('requires_review');

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function userOptions(?int $include = null): array
    {
        return User::query()
            ->where(fn ($q) => $q->where('status', 'active')->when($include, fn ($q) => $q->orWhere('id', $include)))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Task $task): array
    {
        $projects = Project::options();
        if ($task->project_id && ! isset($projects[$task->project_id]) && $task->project) {
            $projects[$task->project_id] = $task->project->code.' — '.$task->project->title;
        }

        $milestones = ProjectMilestone::query()->with('project:id,code')
            ->whereIn('project_id', array_keys($projects))
            ->when($task->project_id, fn ($q, $id) => $q->where('project_id', $id))
            ->orderBy('project_id')->orderBy('sort_order')->orderBy('due_date')->get()
            ->mapWithKeys(fn (ProjectMilestone $m) => [$m->id => ($task->project_id ? '' : $m->project?->code.' — ').$m->title])
            ->all();

        return [
            'task' => $task,
            'projects' => $projects,
            'milestones' => $milestones,
            'users' => $this->userOptions(),
            'departments' => Department::options(),
        ];
    }
}
