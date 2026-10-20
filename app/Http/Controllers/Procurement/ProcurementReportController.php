<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Department;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable procurement register and supplier directory, computed from the
 * recorded requests and suppliers.
 */
class ProcurementReportController extends Controller
{
    public function register(Request $request): View
    {
        $this->authorize('viewAny', ProcurementRequest::class);

        $requests = ProcurementRequestController::filteredQuery($request)
            ->with(['department', 'requester', 'purchaseOrders.supplier'])
            ->orderBy('created_at')
            ->get();

        return view('procurement.reports.register', [
            'requests' => $requests,
            'estimatedByCurrency' => $requests->where('status', '!=', 'cancelled')->groupBy('currency')
                ->map(fn ($items) => $items->sum(fn ($r) => (float) $r->estimated_total))->sortKeys(),
            'orderedByCurrency' => $requests->flatMap->purchaseOrders->where('status', '!=', 'cancelled')->groupBy('currency')
                ->map(fn ($orders) => $orders->sum(fn ($o) => (float) $o->total_amount))->sortKeys(),
            'byStatus' => $requests->countBy('status'),
            'departments' => Department::options(),
            'projects' => Project::options(),
        ]);
    }

    public function suppliers(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = SupplierController::filteredQuery($request)
            ->with('category')
            ->withCount('purchaseOrders')
            ->withAvg('evaluations as quality_avg', 'quality_rating')
            ->withAvg('evaluations as delivery_avg', 'delivery_rating')
            ->withAvg('evaluations as communication_avg', 'communication_rating')
            ->orderBy('name')
            ->get();

        return view('suppliers.report', [
            'suppliers' => $suppliers,
            'categories' => Category::options('supplier'),
        ]);
    }
}
