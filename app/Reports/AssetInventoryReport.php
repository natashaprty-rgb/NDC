<?php

namespace App\Reports;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AssetInventoryReport extends Report
{
    public function key(): string
    {
        return 'assets';
    }

    public function title(): string
    {
        return __('Asset inventory and maintenance');
    }

    public function description(): string
    {
        return __('Registered assets, their allocation, condition, warranty and maintenance history.');
    }

    public function permission(): string
    {
        return 'assets.view';
    }

    public function filters(): array
    {
        return [
            'category' => ['label' => 'Category', 'type' => 'select', 'options' => Category::options('asset')],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => Status::options('asset')],
            'department' => ['label' => 'Department', 'type' => 'select', 'options' => Department::options()],
            'warranty' => ['label' => 'Warranty', 'type' => 'select', 'options' => ['expiring' => __('Expiring within reminder period'), 'expired' => __('Expired')]],
        ];
    }

    public function columns(): array
    {
        return ['Asset tag', 'Name', 'Category', 'Serial number', 'Department', 'Assigned to', 'Location', 'Condition', 'Status', 'Warranty expiry', 'Maintenance records', 'Purchase cost'];
    }

    public function numericColumns(): array
    {
        return [10, 11];
    }

    public function rows(Request $request, User $user): iterable
    {
        return $this->query($request)->with(['category', 'department', 'assignee'])->withCount('maintenances')
            ->orderBy('asset_tag')->get()
            ->map(fn (Asset $a) => [
                $a->asset_tag, $a->name, $a->category?->displayName(), $a->serial_number, $a->department?->displayName(),
                $a->assignee?->name, $a->location, Status::label('asset_condition', $a->condition), Status::label('asset', $a->status),
                fmt_date($a->warranty_expiry), $a->maintenances_count, $a->purchase_cost !== null ? money($a->purchase_cost, $a->currency) : '—',
            ]);
    }

    public function summary(Request $request, User $user): array
    {
        $q = $this->query($request);

        return [
            __('Assets') => (clone $q)->count(),
            __('Assigned') => (clone $q)->where('status', 'assigned')->count(),
            __('Under maintenance') => (clone $q)->where('status', 'under_maintenance')->count(),
            __('Damaged') => (clone $q)->where('status', 'damaged')->count(),
        ];
    }

    private function query(Request $request): Builder
    {
        $days = (int) setting('warranty_reminder_days', 30);

        return Asset::query()
            ->when($request->query('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->query('warranty') === 'expiring', fn ($q) => $q->whereBetween('warranty_expiry', [today(), today()->addDays($days)]))
            ->when($request->query('warranty') === 'expired', fn ($q) => $q->whereDate('warranty_expiry', '<', today()));
    }
}
