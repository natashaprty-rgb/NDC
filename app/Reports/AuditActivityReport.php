<?php

namespace App\Reports;

use App\Models\AuditLog;
use App\Models\LoginActivity;
use App\Models\User;
use Carbon\Carbon;

class AuditActivityReport extends Report
{
    public function key(): string
    {
        return 'audit-activity';
    }

    public function group(): string
    {
        return 'administration';
    }

    public function permissions(): array
    {
        return ['audit.view'];
    }

    public function sensitive(): bool
    {
        return true;
    }

    public function filters(User $user): array
    {
        return [
            'user_id' => ['type' => 'select', 'label' => __('reports.c.user'), 'options' => User::query()->withTrashed()->orderBy('name')->pluck('name', 'id')->all()],
            'event' => ['type' => 'select', 'label' => __('reports.c.event'), 'options' => AuditLog::query()->distinct()->orderBy('event')->pluck('event')->mapWithKeys(fn ($e) => [$e => status_label($e)])->all()],
        ] + $this->dateRange();
    }

    public function build(User $user, array $filters): ReportResult
    {
        // Default period: last 30 days, so the report stays bounded.
        $from = ! empty($filters['date_from']) ? Carbon::parse($filters['date_from'])->startOfDay() : today()->subDays(29);
        $to = ! empty($filters['date_to']) ? Carbon::parse($filters['date_to'])->endOfDay() : now()->endOfDay();

        $logs = AuditLog::query()->with('user')->whereBetween('created_at', [$from, $to])
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when(! empty($filters['event']), fn ($q) => $q->where('event', $filters['event']))
            ->get(['id', 'user_id', 'event', 'created_at']);

        $byUser = $logs->groupBy(fn ($l) => ($l->user?->name ?? __('common.system')).'|'.$l->event)
            ->map(fn ($g, $k) => [explode('|', $k)[0], status_label(explode('|', $k)[1]), $g->count()])
            ->sortBy(fn ($r) => $r[0].$r[1])->values()->all();
        $byDay = $logs->groupBy(fn ($l) => $l->created_at->format('Y-m-d'))->sortKeys()
            ->map(fn ($g, $day) => [fmt_date($day), $g->count()])->values()->all();

        $failures = LoginActivity::query()->whereIn('event', ['failed', 'lockout', 'mfa_failed'])
            ->whereBetween('created_at', [$from, $to])
            ->when(! empty($filters['user_id']), fn ($q) => $q->where('user_id', $filters['user_id']))
            ->latest('id')->limit(500)->get();
        $failureRows = $failures->map(fn ($f) => [fmt_datetime($f->created_at), status_label($f->event), $f->email, $f->ip_address])->all();

        return (new ReportResult)
            ->addSummary(__('reports.s.period'), fmt_date($from).' – '.fmt_date($to), 'text')
            ->addSummary(__('reports.s.audit_events'), $logs->count())
            ->addSummary(__('reports.s.active_users'), $logs->pluck('user_id')->filter()->unique()->count())
            ->addSummary(__('reports.s.login_failures'), $failures->count())
            ->addTable(new ReportTable(__('reports.t.events_by_user'), [$this->col('reports.c.user'), $this->col('reports.c.event'), $this->col('reports.c.count', 'int')], $byUser))
            ->addTable(new ReportTable(__('reports.t.events_by_day'), [$this->col('common.date'), $this->col('reports.c.count', 'int')], $byDay))
            ->addTable(new ReportTable(__('reports.t.login_failures'), [$this->col('common.date'), $this->col('reports.c.event'), $this->col('reports.c.email'), $this->col('reports.c.ip_address')], $failureRows));
    }
}
