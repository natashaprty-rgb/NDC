<?php

namespace App\Reports;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Without tasks.view_all the report covers only tasks the user takes part in (same rule as TaskPolicy::view). */
class TasksReport extends Report
{
    public function key(): string
    {
        return 'tasks';
    }

    public function group(): string
    {
        return 'operations';
    }

    public function filters(User $user): array
    {
        return [
            'status' => ['type' => 'select', 'label' => __('common.status'), 'options' => ['open' => __('reports.f.open')] + enum_options(Task::STATUSES)],
            'overdue' => ['type' => 'select', 'label' => __('common.overdue'), 'options' => ['1' => __('reports.f.overdue_only')]],
            'assigned_to' => ['type' => 'select', 'label' => __('common.assigned_to'), 'options' => User::query()->active()->orderBy('name')->pluck('name', 'id')->all()],
            'department_id' => $this->departmentFilter(),
            'project_id' => $this->projectFilter(),
        ] + $this->dateRange();
    }

    public function visibleQuery(User $user): Builder
    {
        $query = Task::query();
        if ($user->can('tasks.view_all')) {
            return $query;
        }
        $headed = $user->headedDepartmentIds();

        return $query->where(function (Builder $q) use ($user, $headed) {
            $q->where('assigned_to', $user->id)
                ->orWhere('assigned_by', $user->id)
                ->orWhere('created_by', $user->id)
                ->orWhere('approver_id', $user->id)
                ->orWhereHas('project', fn (Builder $p) => $p->where('manager_id', $user->id));
            if ($headed !== []) {
                $q->orWhereIn('department_id', $headed);
            }
        });
    }

    public function build(User $user, array $filters): ReportResult
    {
        $query = $this->visibleQuery($user)
            ->when(($filters['status'] ?? null) === 'open', fn ($q) => $q->open())
            ->when(! empty($filters['status']) && $filters['status'] !== 'open', fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['overdue']), fn ($q) => $q->overdue())
            ->when(! empty($filters['assigned_to']), fn ($q) => $q->where('assigned_to', $filters['assigned_to']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(! empty($filters['project_id']), fn ($q) => $q->where('project_id', $filters['project_id']));
        $this->applyDateRange($query, 'due_date', $filters);

        $tasks = $query->with(['project', 'assignee'])->orderBy('due_date')->orderBy('reference')->get();

        $byStatus = $tasks->countBy('status');
        $statusRows = [];
        foreach (Task::STATUSES as $status) {
            $statusRows[] = [status_label($status), $byStatus->get($status, 0)];
        }

        $rows = [];
        $links = [];
        foreach ($tasks as $i => $t) {
            $rows[] = [$t->reference, $t->title, $t->project?->name, $t->assignee?->name, status_label($t->priority), status_label($t->status),
                fmt_date($t->due_date, ''), $this->yesNo($t->isOverdue()), fmt_date($t->completed_at, '')];
            $links[$i] = route_if('tasks.show', $t);
        }

        return (new ReportResult)
            ->addSummary(__('reports.s.tasks'), $tasks->count())
            ->addSummary(__('reports.s.open_tasks'), $tasks->filter->isOpen()->count())
            ->addSummary(__('reports.s.overdue_tasks'), $tasks->filter->isOverdue()->count())
            ->addSummary(__('reports.s.completed_tasks'), $byStatus->get('completed', 0))
            ->addTable(new ReportTable(__('reports.t.by_status'), [$this->col('common.status'), $this->col('reports.c.count', 'int')], $tasks->isEmpty() ? [] : $statusRows))
            ->addTable(new ReportTable(__('reports.t.task_list'), [
                $this->col('common.reference'), $this->col('common.title'), $this->col('common.project'), $this->col('common.assigned_to'),
                $this->col('reports.c.priority'), $this->col('common.status'), $this->col('common.due', 'date'), $this->col('common.overdue'),
                $this->col('reports.c.completed_at', 'date'),
            ], $rows, $links));
    }
}
