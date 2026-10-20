<?php

namespace App\Http\Controllers\Projects;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use App\Support\Status;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /** Tabs of the project page, in display order. */
    public const TABS = ['overview', 'milestones', 'risks', 'stakeholders', 'team', 'tasks', 'files', 'history'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Project::class);

        $query = Project::query()->with(['department', 'manager'])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->query('manager'), fn ($q, $m) => $q->where('manager_id', $m))
            ->when($request->boolean('delayed'), fn ($q) => $q->delayed());

        match ($request->query('archived')) {
            'archived' => $query->whereNotNull('archived_at'),
            'all' => null,
            default => $query->active(),
        };

        $this->applySort($query, $request, ['code', 'title', 'status', 'progress', 'expected_end_date', 'created_at'], 'created_at');

        return view('projects.index', [
            'projects' => $query->paginate($this->perPage($request))->withQueryString(),
            'departments' => Department::options(),
            'managers' => User::query()->whereIn('id', Project::query()->whereNotNull('manager_id')->select('manager_id'))
                ->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.form', $this->formData(new Project([
            'status' => 'planned',
            'priority' => 'medium',
            'progress' => 0,
            'currency' => setting('currency', 'USD'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $data = $this->validated($request);
        $data['code'] = filled($data['code'] ?? null) ? $data['code'] : $this->generateCode();

        $project = Project::query()->create([...$data, 'created_by' => $request->user()->id]);
        $this->notifyManager($project, $request->user());

        return redirect()->route('projects.show', $project)->with('success', __('Project created.'));
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'overview';
        $project->load(['department', 'manager', 'creator', 'attachments.uploader'])
            ->loadCount(['milestones', 'risks', 'stakeholders', 'members', 'attachments',
                'risks as open_risks_count' => fn ($q) => $q->where('status', '!=', 'closed'),
                'milestones as completed_milestones_count' => fn ($q) => $q->where('status', 'completed'),
            ]);

        $tasks = Task::query()->where('project_id', $project->id)->visibleTo($request->user());

        $data = [
            'project' => $project,
            'tab' => $tab,
            'taskCompletion' => $project->taskCompletion(),
            'visibleTaskCount' => (clone $tasks)->count(),
            'expenditure' => $project->recordedExpenditure(),
        ];

        $data += match ($tab) {
            'milestones' => ['milestones' => $project->milestones()->withCount('tasks')->get()],
            'risks' => ['risks' => $project->risks()->with('owner')->get(), 'users' => $this->userOptions()],
            'stakeholders' => ['stakeholders' => $project->stakeholders()->orderBy('type')->orderBy('name')->get()],
            'team' => [
                'members' => $project->members()->with('department')->orderBy('name')->get(),
                'users' => User::query()->active()->whereNotIn('id', $project->members()->select('users.id'))
                    ->orderBy('name')->pluck('name', 'id'),
            ],
            'tasks' => [
                'tasks' => $tasks->with(['assignee', 'department', 'milestone'])
                    ->orderByRaw("case when status in ('completed', 'cancelled') then 1 else 0 end")
                    ->orderBy('due_date')->paginate(20, pageName: 'tasks_page')->withQueryString(),
            ],
            default => [],
        };

        return view('projects.show', $data);
    }

    /**
     * Printable progress report for the project.
     */
    public function report(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $project->load(['department', 'manager', 'milestones', 'risks.owner', 'stakeholders', 'members']);

        return view('projects.report', [
            'project' => $project,
            'taskCompletion' => $project->taskCompletion(),
            'taskCounts' => $project->tasks()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'overdueTasks' => $project->tasks()->overdue()->count(),
            'expenditure' => $project->recordedExpenditure(),
        ]);
    }

    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.form', $this->formData($project));
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $this->validated($request, $project);
        $previousManager = $project->manager_id;

        $project->update($data);

        if ($project->manager_id !== null && (int) $project->manager_id !== (int) $previousManager) {
            $this->notifyManager($project, $request->user());
        }

        return redirect()->route('projects.show', $project)->with('success', __('Project updated.'));
    }

    public function archive(Project $project): RedirectResponse
    {
        $this->authorize('archive', $project);

        $project->forceFill(['archived_at' => now()])->saveQuietly();
        Audit::log('archived', $project, $project->auditLabel());

        return redirect()->route('projects.show', $project)->with('success', __('Project archived.'));
    }

    public function unarchive(Project $project): RedirectResponse
    {
        $this->authorize('unarchive', $project);

        $project->forceFill(['archived_at' => null])->saveQuietly();
        Audit::log('unarchived', $project, $project->auditLabel());

        return redirect()->route('projects.show', $project)->with('success', __('Project restored from the archive.'));
    }

    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return redirect()->route('projects.index')->with('success', __('Project deleted.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Project $project = null): array
    {
        $data = $request->validate([
            'code' => [$project ? 'required' : 'nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/._-]*$/',
                Rule::unique('projects', 'code')->ignore($project?->id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['nullable', 'string', 'max:10000'],
            'location' => ['nullable', 'string', 'max:255'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'status' => ['required', Rule::in(Status::keys('project'))],
            'priority' => ['required', Rule::in(Status::keys('priority'))],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'planned_budget' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
        ], [
            'code.regex' => __('The code may contain only letters, numbers and the characters / . _ -'),
        ]);

        $data['currency'] = strtoupper($data['currency']);
        $data['planned_budget'] ??= 0;

        if ($data['status'] === 'completed' && empty($data['actual_end_date'])) {
            $data['actual_end_date'] = today()->toDateString();
        }

        return $data;
    }

    /**
     * Next free automatic code, skipping any value already taken manually.
     */
    private function generateCode(): string
    {
        do {
            $code = ReferenceNumber::next('PRJ');
        } while (Project::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    private function notifyManager(Project $project, User $actor): void
    {
        $manager = $project->manager;
        if ($manager === null || $manager->is($actor)) {
            return;
        }

        $manager->notify(new GenericNotification(
            'project_assigned',
            'Project assignment',
            'You have been assigned as project manager of ":title".',
            ['title' => $project->title],
            route('projects.show', $project),
        ));
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
    private function formData(Project $project): array
    {
        $departments = Department::options();
        if ($project->department && ! isset($departments[$project->department_id])) {
            $departments[$project->department_id] = $project->department->displayName();
        }

        return [
            'project' => $project,
            'departments' => $departments,
            'users' => $this->userOptions($project->manager_id),
        ];
    }
}
