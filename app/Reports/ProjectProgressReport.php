<?php

namespace App\Reports;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProjectProgressReport extends Report
{
    public function key(): string
    {
        return 'project-progress';
    }

    public function group(): string
    {
        return 'operations';
    }

    public function permissions(): array
    {
        return ['projects.view'];
    }

    public function filters(User $user): array
    {
        return [
            'status' => $this->statusFilter(Project::STATUSES),
            'department_id' => $this->departmentFilter(),
            'archived' => ['type' => 'select', 'label' => __('reports.f.include_archived'), 'options' => ['1' => __('common.yes')]],
        ];
    }

    public function build(User $user, array $filters): ReportResult
    {
        $projects = Project::query()
            ->with(['manager', 'department'])
            ->withCount([
                'tasks as tasks_total' => fn (Builder $q) => $q->where('status', '!=', 'cancelled'),
                'tasks as tasks_completed' => fn (Builder $q) => $q->where('status', 'completed'),
                'tasks as tasks_overdue' => fn (Builder $q) => $q->overdue(),
                'milestones as milestones_total',
                'milestones as milestones_completed' => fn (Builder $q) => $q->where('status', 'completed'),
                'risks as open_risks' => fn (Builder $q) => $q->where('status', '!=', 'closed'),
            ])
            ->when(empty($filters['archived']), fn ($q) => $q->notArchived())
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->orderBy('reference')
            ->get();

        $rows = [];
        $links = [];
        $overdue = 0;
        $progressTotal = 0;
        foreach ($projects as $i => $p) {
            $progress = $p->tasks_total > 0
                ? intdiv($p->tasks_completed * 100, $p->tasks_total)
                : ($p->milestones_total > 0 ? intdiv($p->milestones_completed * 100, $p->milestones_total) : 0);
            $isOverdue = $p->isOverdue();
            $overdue += $isOverdue ? 1 : 0;
            $progressTotal += $progress;
            $rows[] = [
                $p->reference, $p->name, status_label($p->status), $p->manager?->name, $p->department?->displayName(),
                fmt_date($p->start_date, ''), fmt_date($p->expected_end_date, ''), $this->yesNo($isOverdue),
                $p->tasks_total, $p->tasks_completed, $p->tasks_overdue, $progress, $p->open_risks,
            ];
            $links[$i] = route_if('projects.show', $p);
        }

        return (new ReportResult)
            ->addSummary(__('reports.s.projects'), count($rows))
            ->addSummary(__('reports.s.overdue_projects'), $overdue)
            ->addSummary(__('reports.s.average_progress'), count($rows) ? intdiv($progressTotal, count($rows)) : 0, 'percent')
            ->addTable(new ReportTable(__('reports.r.project-progress.title'), [
                $this->col('common.reference'), $this->col('common.name'), $this->col('common.status'), $this->col('reports.c.manager'),
                $this->col('common.department'), $this->col('reports.c.start_date', 'date'), $this->col('reports.c.expected_end', 'date'),
                $this->col('common.overdue'), $this->col('reports.c.tasks_total', 'int'), $this->col('reports.c.tasks_completed', 'int'),
                $this->col('reports.c.tasks_overdue', 'int'), $this->col('common.progress', 'percent'), $this->col('reports.c.open_risks', 'int'),
            ], $rows, $links));
    }
}
