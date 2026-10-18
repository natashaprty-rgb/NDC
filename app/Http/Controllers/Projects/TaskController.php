<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\TaskRequest;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use App\Services\Projects\TaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TaskController extends Controller
{
    public const SCOPES = ['mine', 'created', 'approvals', 'team', 'all'];

    public function __construct(private readonly TaskService $tasks) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $request->validate([
            'scope' => ['nullable', Rule::in(self::SCOPES)],
            'status' => ['nullable', Rule::in(array_merge(Task::STATUSES, ['open']))],
            'priority' => ['nullable', Rule::in(Task::PRIORITIES)],
            'project' => ['nullable', 'integer'],
        ]);
        $scope = $request->input('scope', 'mine');
        abort_if($scope === 'all' && ! $user->can('tasks.view_all'), 403);

        $query = $this->scopedQuery($scope, $user)
            ->with(['project', 'assignee'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('reference', 'like', $term));
            })
            ->when($request->input('status') === 'open', fn ($q) => $q->open())
            ->when($request->filled('status') && $request->input('status') !== 'open', fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('priority'), fn ($q) => $q->where('priority', $request->input('priority')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->orderByRaw('due_date IS NULL')->orderBy('due_date')->latest('id');

        if ($scope === 'approvals') {
            // Final authority is the policy; filter the candidate set in PHP.
            $ids = (clone $query)->limit(500)->get()->filter(fn (Task $t) => Gate::forUser($user)->allows('approve', $t))->pluck('id');
            $query = Task::query()->with(['project', 'assignee'])->whereIn('id', $ids)->orderBy('submitted_at');
        }

        $tasks = $query->paginate(25)->withQueryString();

        return view('tasks.index', [
            'tasks' => $tasks,
            'scope' => $scope,
            'scopes' => array_values(array_filter(self::SCOPES, fn ($s) => $s !== 'all' || $user->can('tasks.view_all'))),
            'projects' => Project::query()->notArchived()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    /** Candidate tasks for a scope. Every scope only contains tasks the user may view. */
    private function scopedQuery(string $scope, User $user): Builder
    {
        $query = Task::query();

        return match ($scope) {
            'created' => $query->where(fn ($q) => $q->where('created_by', $user->id)->orWhere('assigned_by', $user->id)),
            'approvals' => $query->where('status', 'awaiting_approval')->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', '!=', $user->id)),
            'team' => $query->whereIn('department_id', $user->headedDepartmentIds() ?: [0]),
            'all' => $query,
            default => $query->where('assigned_to', $user->id),
        };
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Task::class);
        $task = new Task([
            'priority' => 'medium',
            'project_id' => $request->integer('project_id') ?: null,
            'department_id' => $request->user()->departmentId(),
        ]);

        return view('tasks.form', ['task' => $task] + $this->formOptions($request->user()));
    }

    public function store(TaskRequest $request): RedirectResponse
    {
        $task = $this->tasks->create($this->payload($request), $request->user());

        return redirect()->route('tasks.show', $task)->with('success', __('common.created'));
    }

    public function show(Request $request, Task $task): View
    {
        $this->authorize('view', $task);
        $task->load(['project', 'milestone', 'department', 'assignee', 'assigner', 'approver', 'approvedBy', 'comments.user', 'creator']);

        return view('tasks.show', ['task' => $task]);
    }

    public function edit(Request $request, Task $task): View
    {
        $this->authorize('update', $task);

        return view('tasks.form', ['task' => $task] + $this->formOptions($request->user()));
    }

    public function update(TaskRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->update($task, $this->payload($request), $request->user());

        return redirect()->route('tasks.show', $task)->with('success', __('common.updated'));
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);
        $task->delete();

        return redirect()->route('tasks.index', ['scope' => 'created'])->with('success', __('common.deleted'));
    }

    private function payload(TaskRequest $request): array
    {
        $data = $request->validated();
        $data['requires_approval'] = $request->boolean('requires_approval');
        if (! $data['requires_approval']) {
            $data['approver_id'] = null;
        }

        return $data;
    }

    private function formOptions(User $user): array
    {
        $projects = Project::query()->notArchived()->orderBy('name')->get()->filter(fn (Project $p) => $user->can('view', $p));
        $milestones = ProjectMilestone::query()->whereIn('project_id', $projects->pluck('id'))->with('project')->orderBy('due_date')->get()
            ->mapWithKeys(fn (ProjectMilestone $m) => [$m->id => $m->project->reference.' — '.$m->title])->all();

        return [
            'projects' => $projects->mapWithKeys(fn (Project $p) => [$p->id => $p->reference.' — '.$p->name])->all(),
            'milestones' => $milestones,
            'departments' => Department::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
