<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\PurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Services\Procurement\PurchaseOrderService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $orders = PurchaseOrder::query()->with(['supplier', 'purchaseRequest'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $term)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', $request->integer('supplier')))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('purchase-orders.index', [
            'orders' => $orders,
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(PurchaseRequest $purchaseRequest): View
    {
        $this->authorize('order', $purchaseRequest);
        $purchaseRequest->load(['items', 'quotations.supplier']);

        $ordered = $this->service->orderedHundredths($purchaseRequest);
        $selected = $purchaseRequest->quotations->firstWhere('is_selected', true);
        $lines = $purchaseRequest->items->map(fn ($item) => [
            'item' => $item,
            'remaining' => Money::fromCents(max(0, Money::toCents((string) $item->quantity) - ($ordered[$item->id] ?? 0))),
        ]);

        return view('purchase-orders.form', [
            'pr' => $purchaseRequest,
            'lines' => $lines,
            'selected' => $selected,
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
            'quotations' => $purchaseRequest->quotations->mapWithKeys(fn ($q) => [$q->id => $q->supplier?->name.' — '.money($q->total_amount, $purchaseRequest->currency)])->all(),
        ]);
    }

    public function store(PurchaseOrderRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $po = $this->service->create($purchaseRequest, $request->validated(), $request->validated('items'));

        return redirect()->route('purchase-orders.show', $po)->with('success', __('procurement.po_created'));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);
        $purchaseOrder->load(['items', 'supplier', 'quotation', 'purchaseRequest.requester', 'receipts.items.orderItem', 'receipts.receiver']);

        return view('purchase-orders.show', ['po' => $purchaseOrder]);
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);
        $this->service->cancel($purchaseOrder);

        return back()->with('success', __('procurement.po_cancelled'));
    }
}
