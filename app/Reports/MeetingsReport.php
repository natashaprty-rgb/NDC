<?php

namespace App\Reports;

use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\User;

class MeetingsReport extends Report
{
    public function key(): string
    {
        return 'meetings';
    }

    public function group(): string
    {
        return 'records';
    }

    public function permissions(): array
    {
        return ['meetings.view'];
    }

    public function filters(User $user): array
    {
        return ['status' => $this->statusFilter(Meeting::STATUSES), 'department_id' => $this->departmentFilter()] + $this->dateRange();
    }

    public function build(User $user, array $filters): ReportResult
    {
        $query = Meeting::query()->with('organizer')
            ->withCount(['items as decisions_count' => fn ($q) => $q->where('type', 'decision'), 'items as actions_count' => fn ($q) => $q->where('type', 'action')])
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']));
        $this->applyDateRange($query, 'starts_at', $filters);
        $meetings = $query->orderBy('starts_at')->get();

        $rows = [];
        $links = [];
        foreach ($meetings as $i => $m) {
            $rows[] = [$m->reference, $m->title, status_label($m->meeting_type), fmt_datetime($m->starts_at, ''), $m->organizer?->name,
                status_label($m->status), $m->decisions_count, $m->actions_count];
            $links[$i] = route_if('meetings.show', $m);
        }

        $items = MeetingItem::query()->with(['meeting', 'responsible'])->whereIn('meeting_id', $meetings->pluck('id'))->orderBy('due_date')->get();
        $itemRows = [];
        $overdue = 0;
        foreach ($items as $item) {
            $isOverdue = $item->type === 'action' && $item->status === 'open' && $item->due_date && $item->due_date->lt(today());
            $overdue += $isOverdue ? 1 : 0;
            $itemRows[] = [$item->meeting?->reference, status_label($item->type), $item->description, $item->responsible?->name,
                fmt_date($item->due_date, ''), status_label($item->status), $this->yesNo($isOverdue)];
        }

        return (new ReportResult)
            ->addSummary(__('reports.s.meetings'), $meetings->count())
            ->addSummary(__('reports.s.decisions'), $items->where('type', 'decision')->count())
            ->addSummary(__('reports.s.open_actions'), $items->where('type', 'action')->where('status', 'open')->count())
            ->addSummary(__('reports.s.overdue_actions'), $overdue)
            ->addTable(new ReportTable(__('reports.t.meetings'), [
                $this->col('common.reference'), $this->col('common.title'), $this->col('common.type'), $this->col('reports.c.starts_at'),
                $this->col('reports.c.organizer'), $this->col('common.status'), $this->col('reports.c.decisions', 'int'), $this->col('reports.c.actions', 'int'),
            ], $rows, $links))
            ->addTable(new ReportTable(__('reports.t.decisions_actions'), [
                $this->col('reports.c.meeting'), $this->col('common.type'), $this->col('common.description'), $this->col('reports.c.responsible'),
                $this->col('common.due', 'date'), $this->col('common.status'), $this->col('common.overdue'),
            ], $itemRows));
    }
}
