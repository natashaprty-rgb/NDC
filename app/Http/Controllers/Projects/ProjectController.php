<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\Budget;
use App\Models\Department;
use App\Models\Document;
use App\Models\Project;
use App\Models\ProjectRisk;
use App\Models\ProjectStakeholder;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $request->validate([
            'status' => ['nullable', Rule::in(Project::STATUSES)],
            'department' => ['nullable', 'integer'],
            'manager' => ['nullable', 'integer'],
        ]);

        $projects = Project::query()
            ->with(['manager', 'department'])
            ->when(! $request->boolean('archived'), fn ($q) => $q->notArchived())
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('reference', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('manager'), fn ($q) => $q->where('manager_id', $request->integer('manager')))
            ->orderByRaw('archived_at IS NOT NULL')
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString();

        return view('projects.index', [
            'projects' => $projects,
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id')->all(),
            'managers' => User::query()->whereIn('id', Project::query()->whereNotNull('manager_id')->select('manager_id'))->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.form', ['project' => new Project(['status' => 'planned', 'priority' => 'medium'])] + $this->formOptions());
    }

    public function store(ProjectRequest $request): RedirectResponse
    {
        $project = DB::transaction(fn () => Project::query()->create($request->validated() + ['reference' => ReferenceNumber::next('project')]));

        return redirect()->route('projects.show', $project)->with('success', __('common.created'));
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $user = $request->user();

        $project->load(['manager', 'department', 'stakeholders.supplier', 'milestones', 'risks.owner']);

        $taskStats = $project->tasks()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v);
        $tasks = $project->tasks()->with('assignee')->orderByRaw("CASE status WHEN 'awaiting_approval' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'todo' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END")->orderBy('due_date')->limit(50)->get()
            ->filter(fn (Task $t) => $user->can('view', $t));

        $budgets = $user->can('budgets.view')
            ? Budget::query()->where('project_id', $project->id)->orderByDesc('fiscal_year')->get()->map(fn (Budget $b) => ['budget' => $b, 'summary' => $b->summary()])
            : collect();

        $documentCount = $user->can('documents.view')
            ? Document::query()->where('project_id', $project->id)->visibleTo($user)->count()
            : null;

        return view('projects.show', [
            'project' => $project,
            'progress' => $project->progressPercent(),
            'taskStats' => $taskStats,
            'tasks' => $tasks,
            'budgets' => $budgets,
            'documentCount' => $documentCount,
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'suppliers' => class_exists(Supplier::class) ? Supplier::query()->orderBy('name')->pluck('name', 'id')->all() : [],
            'stakeholderTypes' => enum_options(ProjectStakeholder::TYPES),
            'riskTypes' => enum_options(ProjectRisk::TYPES),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.form', ['project' => $project] + $this->formOptions());
    }

    public function update(ProjectRequest $request, Project $project): RedirectResponse
    {
        $project->update($request->validated());

        return redirect()->route('projects.show', $project)->with('success', __('common.updated'));
    }

    public function archive(Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);
        $project->update(['archived_at' => now()]);
        AuditLogger::log('archived', $project, 'Project archived');

        return redirect()->route('projects.show', $project)->with('success', __('common.archived'));
    }

    public function restore(Project $project): RedirectResponse
    {
        $this->authorize('restore', $project);
        $project->update(['archived_at' => null]);
        AuditLogger::log('restored', $project, 'Project restored from archive');

        return redirect()->route('projects.show', $project)->with('success', __('common.restored'));
    }

    public function report(Request $request, Project $project): View
    {
        $this->authorize('view', $project);
        $project->load(['manager', 'department', 'milestones', 'risks.owner', 'stakeholders']);

        $taskStats = $project->tasks()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v);

        return view('projects.report', [
            'project' => $project,
            'progress' => $project->progressPercent(),
            'taskStats' => $taskStats,
            'overdueTasks' => $project->tasks()->overdue()->count(),
            'budgets' => $request->user()->can('budgets.view')
                ? Budget::query()->where('project_id', $project->id)->get()->map(fn (Budget $b) => ['budget' => $b, 'summary' => $b->summary()])
                : collect(),
        ]);
    }

    private function formOptions(): array
    {
        return [
            'users' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => Department::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
