<?php

namespace App\Reports;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\User;
use App\Support\Money;

class AssetsReport extends Report
{
    public function key(): string
    {
        return 'assets';
    }

    public function group(): string
    {
        return 'resources';
    }

    public function permissions(): array
    {
        return ['assets.view'];
    }

    public function filters(User $user): array
    {
        return [
            'category_id' => ['type' => 'select', 'label' => __('common.category'), 'options' => AssetCategory::query()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all()],
            'status' => $this->statusFilter(Asset::STATUSES),
            'location_id' => ['type' => 'select', 'label' => __('reports.c.location'), 'options' => Location::query()->orderBy('name')->pluck('name', 'id')->all()],
            'department_id' => $this->departmentFilter(),
        ];
    }

    public function build(User $user, array $filters): ReportResult
    {
        $assets = Asset::query()->with(['category', 'location', 'assignedStaff'])
            ->when(! empty($filters['category_id']), fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['location_id']), fn ($q) => $q->where('location_id', $filters['location_id']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->orderBy('asset_tag')->get();

        $grouped = function ($groups) {
            return $groups->map(fn ($items, $label) => [$label, $items->count(), Money::sum($items->pluck('purchase_cost')->map(fn ($v) => $v ?? '0'))])->values()->all();
        };
        $countCostCols = fn (string $key) => [$this->col($key), $this->col('reports.c.count', 'int'), $this->col('reports.c.purchase_cost', 'money')];

        $warranty = $assets->filter(fn ($a) => ! $a->isRetired() && $a->warranty_expiry && $a->warranty_expiry->between(today(), today()->addDays(90)))->values();
        $warrantyRows = [];
        $links = [];
        foreach ($warranty as $i => $a) {
            $warrantyRows[] = [$a->asset_tag, $a->name, $a->category?->displayName(), $a->serial_number, fmt_date($a->warranty_expiry, ''), $a->location?->name];
            $links[$i] = route_if('assets.show', $a);
        }

        $total = Money::sum($assets->pluck('purchase_cost')->map(fn ($v) => $v ?? '0'));

        return (new ReportResult)
            ->addSummary(__('reports.s.assets'), $assets->count())
            ->addSummary(__('reports.s.in_use'), $assets->where('status', 'in_use')->count())
            ->addSummary(__('reports.s.total_purchase_cost'), $total, 'money')
            ->addSummary(__('reports.s.warranty_expiring'), $warranty->count())
            ->addTable(new ReportTable(__('reports.t.by_category'), $countCostCols('common.category'),
                $grouped($assets->groupBy(fn ($a) => $a->category?->displayName() ?? '—')->sortKeys())))
            ->addTable(new ReportTable(__('reports.t.by_status'), $countCostCols('common.status'),
                $grouped($assets->groupBy(fn ($a) => status_label($a->status)))))
            ->addTable(new ReportTable(__('reports.t.by_location'), $countCostCols('reports.c.location'),
                $grouped($assets->groupBy(fn ($a) => $a->location?->name ?? __('reports.unassigned'))->sortKeys())))
            ->addTable(new ReportTable(__('reports.t.warranty_expiring'), [
                $this->col('reports.c.asset_tag'), $this->col('common.name'), $this->col('common.category'), $this->col('reports.c.serial_number'),
                $this->col('reports.c.warranty_expiry', 'date'), $this->col('reports.c.location'),
            ], $warrantyRows, $links));
    }
}
