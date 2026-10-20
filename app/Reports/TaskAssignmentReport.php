<?php

namespace App\Reports;

use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Task assignment and completion per staff member. Only tasks the viewer may
 * see are counted.
 */
class TaskAssignmentReport extends Report
{
    public function key(): string
    {
        return 'tasks';
    }

    public function title(): string
    {
        return __('Staff task assignment and completion');
    }

    public function description(): string
    {
        return __('Tasks assigned to each person with completion and overdue counts.');
    }

    public function permission(): string
    {
        return 'tasks.view';
    }

    public function filters(): array
    {
        return [
            'department' => ['label' => 'Department', 'type' => 'select', 'options' => Department::options()],
            'project' => ['label' => 'Project', 'type' => 'select', 'options' => Project::options()],
            ...$this->dateRange('Due date'),
        ];
    }

    public function columns(): array
    {
        return ['Assigned to', 'Department', 'Total', 'Open', 'Awaiting review', 'Completed', 'Overdue', 'Completion %'];
    }

    public function numericColumns(): array
    {
        return [2, 3, 4, 5, 6, 7];
    }

    public function rows(Request $request, User $user): iterable
    {
        $tasks = $this->tasks($request, $user)->with(['assignee.department', 'department'])->get();

        return $tasks->groupBy(fn (Task $t) => $t->assigned_to ? 'u'.$t->assigned_to : 'd'.$t->department_id)
            ->map(function ($group) {
                $first = $group->first();
                $total = $group->count();
                $completed = $group->where('status', 'completed')->count();

                return [
                    $first->assignee?->name ?? __('Unassigned (department)'),
                    ($first->assignee?->department ?? $first->department)?->displayName(),
                    $total,
                    $group->filter->isOpen()->count(),
                    $group->where('status', 'submitted')->count(),
                    $completed,
                    $group->filter->isOverdue()->count(),
                    $total ? round($completed / $total * 100) : 0,
                ];
            })
            ->sortBy(fn ($row) => mb_strtolower((string) $row[0]))->values();
    }

    public function summary(Request $request, User $user): array
    {
        $q = $this->tasks($request, $user);

        return [
            __('Tasks') => (clone $q)->count(),
            __('Completed') => (clone $q)->where('status', 'completed')->count(),
            __('Overdue') => (clone $q)->overdue()->count(),
        ];
    }

    private function tasks(Request $request, User $user)
    {
        return Task::query()->visibleTo($user)
            ->when($request->query('department'), fn ($q, $d) => $q->where(fn ($q) => $q->where('tasks.department_id', $d)
                ->orWhereHas('assignee', fn ($a) => $a->where('department_id', $d))))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('due_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('due_date', '<=', $d));
    }
}
