<?php

namespace App\Reports;

use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProjectProgressReport extends Report
{
    public function key(): string
    {
        return 'projects';
    }

    public function title(): string
    {
        return __('Project progress and implementation');
    }

    public function description(): string
    {
        return __('Status, schedule, progress and budget position of each project.');
    }

    public function permission(): string
    {
        return 'projects.view';
    }

    public function filters(): array
    {
        return [
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => Status::options('project')],
            'department' => ['label' => 'Department', 'type' => 'select', 'options' => Department::options()],
            'delayed' => ['label' => 'Schedule', 'type' => 'select', 'options' => ['1' => __('Delayed only')]],
            'archived' => ['label' => 'Archived', 'type' => 'select', 'options' => ['1' => __('Include archived')]],
        ];
    }

    public function columns(): array
    {
        return ['Code', 'Project', 'Department', 'Manager', 'Status', 'Start', 'Expected completion', 'Progress %', 'Tasks done', 'Planned budget', 'Recorded expenditure', 'Delayed'];
    }

    public function numericColumns(): array
    {
        return [7, 9, 10];
    }

    public function rows(Request $request, User $user): iterable
    {
        return $this->query($request)->with(['department', 'manager'])
            ->withCount(['tasks', 'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed')])
            ->orderBy('code')->get()
            ->map(fn (Project $p) => [
                $p->code, $p->title, $p->department?->displayName(), $p->manager?->name,
                Status::label('project', $p->status), fmt_date($p->start_date), fmt_date($p->expected_end_date),
                $p->progress, $p->completed_tasks_count.' / '.$p->tasks_count,
                money($p->planned_budget, $p->currency), money($p->recordedExpenditure(), $p->currency),
                $p->isDelayed() ? __('Yes') : __('No'),
            ]);
    }

    public function summary(Request $request, User $user): array
    {
        $q = $this->query($request);

        return [
            __('Projects') => (clone $q)->count(),
            __('In progress') => (clone $q)->where('status', 'in_progress')->count(),
            __('Completed') => (clone $q)->where('status', 'completed')->count(),
            __('Delayed') => (clone $q)->delayed()->count(),
        ];
    }

    private function query(Request $request): Builder
    {
        return Project::query()
            ->when(! $request->query('archived'), fn ($q) => $q->active())
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->query('delayed'), fn ($q) => $q->delayed());
    }
}
