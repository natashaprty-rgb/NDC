<?php

namespace App\Reports;

use App\Models\Department;
use App\Models\ProcurementRequest;
use App\Models\User;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProcurementReport extends Report
{
    public function key(): string
    {
        return 'procurement';
    }

    public function title(): string
    {
        return __('Procurement and suppliers');
    }

    public function description(): string
    {
        return __('Purchase requests from request to delivery, with orders and suppliers.');
    }

    public function permission(): string
    {
        return 'procurement.view';
    }

    public function filters(): array
    {
        return [
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => Status::options('procurement')],
            'department' => ['label' => 'Department', 'type' => 'select', 'options' => Department::options()],
            ...$this->dateRange('Request date'),
        ];
    }

    public function columns(): array
    {
        return ['Reference', 'Title', 'Department', 'Requested by', 'Requested', 'Status', 'Estimated total', 'Purchase orders', 'Supplier(s)', 'Ordered total', 'Items outstanding'];
    }

    public function numericColumns(): array
    {
        return [6, 9, 10];
    }

    public function rows(Request $request, User $user): iterable
    {
        return $this->query($request)->with(['department', 'requester', 'purchaseOrders.supplier', 'items'])
            ->orderBy('created_at')->get()
            ->map(fn (ProcurementRequest $r) => [
                $r->reference_number, $r->title, $r->department?->displayName(), $r->requester?->name, fmt_date($r->created_at),
                Status::label('procurement', $r->status), money($r->estimated_total, $r->currency),
                $r->purchaseOrders->pluck('po_number')->join(', ') ?: '—',
                $r->purchaseOrders->map(fn ($po) => $po->supplier?->name)->filter()->unique()->join(', ') ?: '—',
                money($r->purchaseOrders->where('status', '!=', 'cancelled')->sum('total_amount'), $r->currency),
                $r->items->filter(fn ($i) => $i->outstandingQuantity() > 0)->count(),
            ]);
    }

    public function summary(Request $request, User $user): array
    {
        $q = $this->query($request);

        return [
            __('Requests') => (clone $q)->count(),
            __('Awaiting approval') => (clone $q)->where('status', 'submitted')->count(),
            __('Ordered / in delivery') => (clone $q)->whereIn('status', ['ordered', 'partially_received'])->count(),
            __('Received') => (clone $q)->where('status', 'received')->count(),
        ];
    }

    private function query(Request $request): Builder
    {
        return ProcurementRequest::query()
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
    }
}
