<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Staff;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only integration API (v1). Each endpoint requires the module's view
 * permission and applies the same record-level visibility as the web
 * interface. Sensitive personal data and file contents are never exposed.
 */
class ApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'department_id' => $user->department_id,
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]]);
    }

    public function projects(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'projects.view');

        $query = Project::query()->active()->with(['department', 'manager'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('updated_since'), fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->orderBy('code');

        return $this->paginated($request, $query, fn (Project $p) => $this->project($p));
    }

    public function showProject(Request $request, Project $project): JsonResponse
    {
        $this->requirePermission($request, 'projects.view');
        $project->load(['department', 'manager', 'milestones']);

        return response()->json(['data' => $this->project($project) + [
            'milestones' => $project->milestones->map(fn ($m) => [
                'id' => $m->id, 'title' => $m->title, 'due_date' => $m->due_date?->toDateString(),
                'status' => $m->status, 'completed_at' => $m->completed_at?->toIso8601String(),
            ]),
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function project(Project $project): array
    {
        return [
            'id' => $project->id,
            'code' => $project->code,
            'title' => $project->title,
            'status' => $project->status,
            'priority' => $project->priority,
            'progress' => $project->progress,
            'department' => $project->department?->name,
            'manager' => $project->manager?->name,
            'location' => $project->location,
            'start_date' => $project->start_date?->toDateString(),
            'expected_end_date' => $project->expected_end_date?->toDateString(),
            'actual_end_date' => $project->actual_end_date?->toDateString(),
            'planned_budget' => (float) $project->planned_budget,
            'currency' => $project->currency,
            'is_delayed' => $project->isDelayed(),
            'updated_at' => $project->updated_at?->toIso8601String(),
        ];
    }

    public function tasks(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'tasks.view');

        $query = Task::query()->visibleTo($request->user())->with(['assignee', 'project'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('project_id'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->query('updated_since'), fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->orderBy('due_date');

        return $this->paginated($request, $query, fn (Task $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'project_code' => $t->project?->code,
            'assigned_to' => $t->assignee?->name,
            'department_id' => $t->department_id,
            'priority' => $t->priority,
            'status' => $t->status,
            'progress' => $t->progress,
            'due_date' => $t->due_date?->toDateString(),
            'is_overdue' => $t->isOverdue(),
            'completed_at' => $t->completed_at?->toIso8601String(),
            'updated_at' => $t->updated_at?->toIso8601String(),
        ]);
    }

    public function documents(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'documents.view');

        $query = Document::query()->visibleTo($request->user())->active()->with(['category', 'department'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('updated_since'), fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->orderBy('reference_number');

        return $this->paginated($request, $query, fn (Document $d) => [
            'id' => $d->id,
            'reference_number' => $d->reference_number,
            'title' => $d->title,
            'type' => $d->category?->name,
            'department' => $d->department?->name,
            'confidentiality' => $d->confidentiality,
            'status' => $d->status,
            'current_version' => $d->current_version,
            'document_date' => $d->document_date?->toDateString(),
            'expiry_date' => $d->expiry_date?->toDateString(),
            'updated_at' => $d->updated_at?->toIso8601String(),
        ]);
    }

    public function assets(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'assets.view');

        $query = Asset::query()->with(['category', 'department'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('updated_since'), fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->orderBy('asset_tag');

        return $this->paginated($request, $query, fn (Asset $a) => [
            'id' => $a->id,
            'asset_tag' => $a->asset_tag,
            'name' => $a->name,
            'category' => $a->category?->name,
            'manufacturer' => $a->manufacturer,
            'model' => $a->model,
            'serial_number' => $a->serial_number,
            'department' => $a->department?->name,
            'location' => $a->location,
            'condition' => $a->condition,
            'status' => $a->status,
            'warranty_expiry' => $a->warranty_expiry?->toDateString(),
            'specifications' => $a->specifications,
            'updated_at' => $a->updated_at?->toIso8601String(),
        ]);
    }

    public function staff(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'staff.view');

        $query = Staff::query()->with('department')
            ->when($request->query('employment_status'), fn ($q, $s) => $q->where('employment_status', $s))
            ->orderBy('full_name');

        // Directory fields only; sensitive personal data is never returned.
        return $this->paginated($request, $query, fn (Staff $s) => [
            'id' => $s->id,
            'staff_number' => $s->staff_number,
            'full_name' => $s->full_name,
            'job_title' => $s->job_title,
            'department' => $s->department?->name,
            'email' => $s->email,
            'phone' => $s->phone,
            'employment_status' => $s->employment_status,
        ]);
    }

    public function meetings(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'meetings.view');

        $query = Meeting::query()
            ->when($request->query('from'), fn ($q, $d) => $q->where('start_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->where('start_at', '<=', $d))
            ->orderBy('start_at');

        return $this->paginated($request, $query, fn (Meeting $m) => [
            'id' => $m->id,
            'title' => $m->title,
            'type' => $m->type,
            'start_at' => $m->start_at?->toIso8601String(),
            'end_at' => $m->end_at?->toIso8601String(),
            'location' => $m->location,
            'status' => $m->status,
        ]);
    }

    private function requirePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->can($permission), 403, 'This token\'s user lacks the '.$permission.' permission.');
        abort_unless($request->user()->tokenCan('read'), 403, 'This token does not have the read ability.');
    }

    private function paginated(Request $request, Builder $query, callable $map): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'updated_since' => ['nullable', 'date'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        /** @var LengthAwarePaginator $page */
        $page = $query->paginate((int) $request->query('per_page', 25))->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map($map)->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
        ]);
    }
}
