<?php

namespace App\Reports;

use App\Models\Correspondence;
use App\Models\User;

/** Uses Correspondence::visibleTo(), the same rule as the registers. */
class CorrespondenceReport extends Report
{
    public function key(): string
    {
        return 'correspondence';
    }

    public function group(): string
    {
        return 'records';
    }

    public function permissions(): array
    {
        return ['correspondence.view'];
    }

    public function filters(User $user): array
    {
        return [
            'direction' => ['type' => 'select', 'label' => __('reports.c.direction'), 'options' => enum_options(Correspondence::DIRECTIONS)],
            'department_id' => $this->departmentFilter(),
        ] + $this->dateRange();
    }

    public function build(User $user, array $filters): ReportResult
    {
        $query = Correspondence::query()->visibleTo($user)->with(['assignee', 'department'])
            ->when(! empty($filters['direction']), fn ($q) => $q->where('direction', $filters['direction']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']));
        $this->applyDateRange($query, 'received_or_sent_date', $filters);
        $items = $query->orderBy('received_or_sent_date')->get();

        $periods = [];
        foreach ($items->groupBy(fn ($c) => $c->received_or_sent_date->format('Y-m'))->sortKeys() as $period => $group) {
            $periods[] = [$period, $group->where('direction', 'incoming')->count(), $group->where('direction', 'outgoing')->count(),
                $group->whereIn('status', Correspondence::OPEN_STATUSES)->count()];
        }

        $closed = $items->filter(fn ($c) => $c->closed_at !== null);
        $avgDays = $closed->isEmpty() ? null
            : round($closed->avg(fn ($c) => max(0, $c->received_or_sent_date->copy()->startOfDay()->diffInDays($c->closed_at->copy()->startOfDay()))), 1);

        $overdue = $items->filter->isOverdue()->values();
        $rows = [];
        $links = [];
        foreach ($overdue as $i => $c) {
            $rows[] = [$c->reference, status_label($c->direction), $c->subject, $c->assignee?->name, fmt_date($c->response_deadline, ''), status_label($c->status)];
            $links[$i] = route_if('correspondence.show', $c);
        }

        return (new ReportResult)
            ->addSummary(__('reports.s.incoming'), $items->where('direction', 'incoming')->count())
            ->addSummary(__('reports.s.outgoing'), $items->where('direction', 'outgoing')->count())
            ->addSummary(__('reports.s.open'), $items->whereIn('status', Correspondence::OPEN_STATUSES)->count())
            ->addSummary(__('reports.s.overdue'), $overdue->count())
            ->addSummary(__('reports.s.avg_days_to_close'), $avgDays ?? '—', 'text')
            ->addTable(new ReportTable(__('reports.t.by_period'), [
                $this->col('reports.c.period'), $this->col('reports.c.incoming', 'int'), $this->col('reports.c.outgoing', 'int'), $this->col('reports.c.open', 'int'),
            ], $periods))
            ->addTable(new ReportTable(__('reports.t.overdue_responses'), [
                $this->col('common.reference'), $this->col('reports.c.direction'), $this->col('reports.c.subject'), $this->col('common.assigned_to'),
                $this->col('reports.c.deadline', 'date'), $this->col('common.status'),
            ], $rows, $links));
    }
}
