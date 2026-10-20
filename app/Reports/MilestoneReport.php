<?php

namespace App\Reports;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\User;
use App\Support\Status;
use Illuminate\Http\Request;

class MilestoneReport extends Report
{
    public function key(): string
    {
        return 'milestones';
    }

    public function title(): string
    {
        return __('Project milestones and deadlines');
    }

    public function description(): string
    {
        return __('Milestones by due date, including overdue items.');
    }

    public function permission(): string
    {
        return 'projects.view';
    }

    public function filters(): array
    {
        return [
            'project' => ['label' => 'Project', 'type' => 'select', 'options' => Project::options()],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => Status::options('milestone')],
            'overdue' => ['label' => 'Schedule', 'type' => 'select', 'options' => ['1' => __('Overdue only')]],
            ...$this->dateRange('Due date'),
        ];
    }

    public function columns(): array
    {
        return ['Project', 'Milestone', 'Deliverables', 'Due date', 'Status', 'Completed', 'Overdue'];
    }

    public function rows(Request $request, User $user): iterable
    {
        return ProjectMilestone::query()->with('project')
            ->whereHas('project', fn ($q) => $q->whereNull('archived_at'))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('overdue'), fn ($q) => $q->where('status', '!=', 'completed')->whereDate('due_date', '<', today()))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('due_date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('due_date', '<=', $d))
            ->orderBy('due_date')->get()
            ->map(fn (ProjectMilestone $m) => [
                $m->project?->code, $m->title, $m->deliverables, fmt_date($m->due_date),
                Status::label('milestone', $m->status), fmt_date($m->completed_at), $m->isOverdue() ? __('Yes') : __('No'),
            ]);
    }
}
