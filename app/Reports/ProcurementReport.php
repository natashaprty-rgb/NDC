<?php

namespace App\Reports;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\User;
use App\Support\Money;

class ProcurementReport extends Report
{
    public function key(): string
    {
        return 'procurement';
    }

    public function group(): string
    {
        return 'finance';
    }

    public function permissions(): array
    {
        return ['reports.financial'];
    }

    public function filters(User $user): array
    {
        return ['department_id' => $this->departmentFilter(), 'project_id' => $this->projectFilter()] + $this->dateRange();
    }

    public function build(User $user, array $filters): ReportResult
    {
        $prQuery = PurchaseRequest::query()
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(! empty($filters['project_id']), fn ($q) => $q->where('project_id', $filters['project_id']));
        $this->applyDateRange($prQuery, 'created_at', $filters);
        $requests = $prQuery->get(['id', 'status', 'estimated_total']);

        $prRows = [];
        foreach (PurchaseRequest::STATUSES as $status) {
            $group = $requests->where('status', $status);
            if ($group->isNotEmpty()) {
                $prRows[] = [status_label($status), $group->count(), Money::sum($group->pluck('estimated_total'))];
            }
        }

        $orders = PurchaseOrder::query()->with('supplier')->whereIn('purchase_request_id', $requests->pluck('id'))->get();
        $poRows = [];
        foreach (PurchaseOrder::STATUSES as $status) {
            $group = $orders->where('status', $status);
            if ($group->isNotEmpty()) {
                $poRows[] = [status_label($status), $group->count(), Money::sum($group->pluck('total_amount'))];
            }
        }

        $active = $orders->where('status', '!=', 'cancelled');
        $supplierRows = $active->groupBy('supplier_id')->map(fn ($group) => [
            $group->first()->supplier?->name ?? '—', $group->count(), Money::sum($group->pluck('total_amount')),
        ])->sortByDesc(fn ($row) => Money::toCents($row[2]))->values()->all();

        $countAmount = fn (string $key, string $amountKey) => [$this->col($key), $this->col('reports.c.count', 'int'), $this->col($amountKey, 'money')];

        return (new ReportResult)
            ->addSummary(__('reports.s.purchase_requests'), $requests->count())
            ->addSummary(__('reports.s.estimated_total'), Money::sum($requests->where('status', '!=', 'cancelled')->pluck('estimated_total')), 'money')
            ->addSummary(__('reports.s.purchase_orders'), $active->count())
            ->addSummary(__('reports.s.ordered_total'), Money::sum($active->pluck('total_amount')), 'money')
            ->addTable(new ReportTable(__('reports.t.requests_by_status'), $countAmount('common.status', 'reports.c.estimated_total'), $prRows))
            ->addTable(new ReportTable(__('reports.t.orders_by_status'), $countAmount('common.status', 'reports.c.order_total'), $poRows))
            ->addTable(new ReportTable(__('reports.t.supplier_summary'), $countAmount('reports.c.supplier', 'reports.c.order_total'), $supplierRows));
    }
}
