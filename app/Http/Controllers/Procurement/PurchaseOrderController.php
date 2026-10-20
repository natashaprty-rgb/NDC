<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ProcurementRequest;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Support\Audit;
use App\Support\FileStore;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Purchase orders and contracts issued from approved purchase requests. The
 * system records orders only; it never makes payments.
 */
class PurchaseOrderController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $query = PurchaseOrder::query()->with(['supplier', 'request'])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('po_number', 'like', "%{$term}%")
                ->orWhere('contract_reference', 'like', "%{$term}%")
                ->orWhereHas('request', fn ($q) => $q->where('title', 'like', "%{$term}%")->orWhere('reference_number', 'like', "%{$term}%"))))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('supplier'), fn ($q, $v) => $q->where('supplier_id', $v))
            ->when($request->query('overdue'), fn ($q) => $q->whereIn('status', ['issued', 'partially_delivered'])
                ->whereDate('expected_delivery_date', '<', today()));
        $this->applySort($query, $request, ['po_number', 'order_date', 'expected_delivery_date', 'total_amount', 'status'], 'order_date');

        return view('procurement.orders.index', [
            'orders' => $query->paginate($this->perPage($request))->withQueryString(),
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(ProcurementRequest $procurementRequest): View
    {
        $this->authorize('manage', $procurementRequest);

        $procurementRequest->load(['quotations.supplier', 'items']);
        $selected = $procurementRequest->quotations->firstWhere('is_selected', true);

        $order = new PurchaseOrder([
            'supplier_id' => $selected?->supplier_id,
            'quotation_id' => $selected?->id,
            'order_date' => today(),
            'total_amount' => $selected?->amount ?? $procurementRequest->estimated_total,
            'currency' => $selected?->currency ?? $procurementRequest->currency,
        ]);

        return view('procurement.orders.form', $this->formData($procurementRequest, $order));
    }

    public function store(Request $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->authorize('manage', $procurementRequest);
        $data = $this->validated($request, $procurementRequest);

        $order = DB::transaction(function () use ($procurementRequest, $data, $request) {
            /** @var PurchaseOrder $order */
            $order = $procurementRequest->purchaseOrders()->create([
                ...collect($data)->except('file')->all(),
                'po_number' => ReferenceNumber::next('PO'),
                'status' => 'issued',
                'created_by' => $request->user()->id,
            ]);
            if ($request->hasFile('file')) {
                FileStore::attach($order, $request->file('file'), 'contract');
            }
            $procurementRequest->forceFill(['status' => 'ordered'])->save();

            return $order;
        });

        Audit::log('ordered', $procurementRequest, $order->po_number);

        return redirect()->route('purchase-orders.show', $order)->with('success', __('Purchase order :number issued.', ['number' => $order->po_number]));
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('view', $purchaseOrder);

        $purchaseOrder->load([
            'request.items', 'request.department', 'supplier', 'quotation', 'creator', 'attachments.uploader',
            'receipts.items.requestItem', 'receipts.receiver', 'receipts.attachments', 'evaluations.evaluator',
        ]);

        return view('procurement.orders.show', ['order' => $purchaseOrder, 'pr' => $purchaseOrder->request]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $this->authorize('update', $purchaseOrder);

        $purchaseOrder->request->load(['quotations.supplier', 'items']);

        return view('procurement.orders.form', $this->formData($purchaseOrder->request, $purchaseOrder));
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('update', $purchaseOrder);
        $data = $this->validated($request, $purchaseOrder->request, $purchaseOrder);

        DB::transaction(function () use ($purchaseOrder, $data, $request) {
            $purchaseOrder->update(collect($data)->except('file')->all());
            if ($request->hasFile('file')) {
                FileStore::attach($purchaseOrder, $request->file('file'), 'contract');
            }
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', __('Purchase order updated.'));
    }

    /**
     * Cancel an order before any delivery; the request returns to "approved"
     * so a new order can be raised.
     */
    public function cancel(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($purchaseOrder) {
            $purchaseOrder->update(['status' => 'cancelled']);
            $pr = $purchaseOrder->request;
            if ($pr->status === 'ordered' && ! $pr->activePurchaseOrder()->exists()) {
                $pr->forceFill(['status' => 'approved'])->save();
            }
        });

        Audit::log('cancelled', $purchaseOrder, $data['reason']);

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', __('Purchase order cancelled.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ProcurementRequest $pr, ?PurchaseOrder $order = null): array
    {
        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'quotation_id' => ['nullable', Rule::exists('quotations', 'id')->where('procurement_request_id', $pr->id)],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => array_filter(['nullable', 'date', $request->filled('order_date') ? 'after_or_equal:order_date' : null]),
            'total_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'contract_reference' => ['nullable', 'string', 'max:255'],
            'contract_end_date' => array_filter(['nullable', 'date', $request->filled('order_date') ? 'after_or_equal:order_date' : null]),
            'terms' => ['nullable', 'string', 'max:10000'],
            'file' => FileStore::rules(),
        ]);

        // A linked quotation must come from the ordered supplier.
        if (! empty($data['quotation_id']) && (int) $pr->quotations()->whereKey($data['quotation_id'])->value('supplier_id') !== (int) $data['supplier_id']) {
            throw ValidationException::withMessages([
                'quotation_id' => __('The selected quotation was submitted by a different supplier.'),
            ]);
        }

        $data['currency'] = strtoupper($data['currency']);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ProcurementRequest $pr, PurchaseOrder $order): array
    {
        $suppliers = Supplier::options();
        if ($order->supplier_id && ! isset($suppliers[$order->supplier_id])) {
            $suppliers[$order->supplier_id] = Supplier::withTrashed()->find($order->supplier_id)?->name ?? '#'.$order->supplier_id;
        }

        return [
            'pr' => $pr,
            'order' => $order,
            'suppliers' => $suppliers,
            'quotations' => $pr->quotations->mapWithKeys(fn ($q) => [
                $q->id => $q->supplier?->name.' — '.money($q->amount, $q->currency).($q->is_selected ? ' ('.__('selected').')' : ''),
            ])->all(),
        ];
    }
}
