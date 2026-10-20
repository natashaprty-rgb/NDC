<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\Department;
use App\Models\GoodsReceipt;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\ApprovalService;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Purchase requests: draft -> submitted -> approved/rejected -> ordered ->
 * partially_received -> received (or cancelled). Approval always needs a
 * second person holding procurement.approve.
 */
class ProcurementRequestController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', ProcurementRequest::class);

        $query = self::filteredQuery($request)->with(['department', 'requester'])->withCount('items');
        $this->applySort($query, $request, ['reference_number', 'title', 'status', 'estimated_total', 'needed_by', 'created_at'], 'created_at');

        return view('procurement.index', [
            'requests' => $query->paginate($this->perPage($request))->withQueryString(),
            'summary' => ProcurementRequest::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'departments' => Department::options(),
            'projects' => Project::options(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ProcurementRequest::class);

        return view('procurement.form', $this->formData(new ProcurementRequest([
            'department_id' => $request->user()->department_id,
            'currency' => setting('currency', 'USD'),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ProcurementRequest::class);
        [$data, $items] = $this->validated($request);

        $procurement = DB::transaction(function () use ($data, $items, $request) {
            $procurement = new ProcurementRequest([
                ...$data,
                'reference_number' => ReferenceNumber::next('PR'),
                'requested_by' => $request->user()->id,
                'status' => 'draft',
            ]);
            $procurement->save();
            $procurement->items()->createMany($items);
            $procurement->recalculateTotal();

            return $procurement;
        });

        return redirect()->route('procurement.show', $procurement)
            ->with('success', __('Purchase request :ref saved as a draft. Submit it when it is ready for approval.', ['ref' => $procurement->reference_number]));
    }

    public function show(ProcurementRequest $procurementRequest): View
    {
        $this->authorize('view', $procurementRequest);

        $procurementRequest->load([
            'department', 'project', 'budget', 'requester', 'items', 'attachments.uploader',
            'quotations.supplier', 'quotations.attachments',
            'purchaseOrders.supplier', 'purchaseOrders.receipts',
        ]);

        return view('procurement.show', [
            'pr' => $procurementRequest,
            'timeline' => $this->timeline($procurementRequest),
            'suppliers' => Supplier::options(),
        ]);
    }

    public function edit(ProcurementRequest $procurementRequest): View
    {
        $this->authorize('edit', $procurementRequest);

        return view('procurement.form', $this->formData($procurementRequest->load('items')));
    }

    public function update(Request $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->authorize('edit', $procurementRequest);
        [$data, $items] = $this->validated($request);

        DB::transaction(function () use ($procurementRequest, $data, $items) {
            $procurementRequest->update($data);
            $procurementRequest->items()->delete();
            $procurementRequest->items()->createMany($items);
            $procurementRequest->recalculateTotal();
        });

        return redirect()->route('procurement.show', $procurementRequest)->with('success', __('Purchase request updated.'));
    }

    public function destroy(ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->authorize('delete', $procurementRequest);

        $procurementRequest->delete();

        return redirect()->route('procurement.index')->with('success', __('Purchase request deleted.'));
    }

    /**
     * Send the request for approval. Nothing is approved automatically.
     */
    public function submit(Request $request, ProcurementRequest $procurementRequest, ApprovalService $approvals): RedirectResponse
    {
        $this->authorize('submit', $procurementRequest);

        if (! $procurementRequest->items()->exists()) {
            return back()->with('error', __('Add at least one item before submitting.'));
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        DB::transaction(function () use ($procurementRequest, $request, $approvals, $data) {
            $procurementRequest->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();
            $approvals->request($procurementRequest, 'procurement.approve', $request->user(), $data['note'] ?? null);
        });

        Audit::log('submitted', $procurementRequest, $procurementRequest->title);

        return back()->with('success', __('Purchase request submitted for approval.'));
    }

    public function cancel(Request $request, ProcurementRequest $procurementRequest, ApprovalService $approvals): RedirectResponse
    {
        $this->authorize('cancel', $procurementRequest);

        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        DB::transaction(function () use ($procurementRequest, $request, $approvals) {
            $pending = $procurementRequest->approvals()->where('status', 'pending')->first();
            if ($pending) {
                $approvals->cancel($pending, $request->user());
            }
            $procurementRequest->purchaseOrders()->where('status', 'issued')->get()
                ->each(fn (PurchaseOrder $po) => $po->forceFill(['status' => 'cancelled'])->save());
            $procurementRequest->forceFill(['status' => 'cancelled'])->save();
        });

        Audit::log('cancelled', $procurementRequest, $data['reason']);

        return back()->with('success', __('Purchase request cancelled.'));
    }

    /**
     * List query with filters; shared with the printable register.
     */
    public static function filteredQuery(Request $request): Builder
    {
        return ProcurementRequest::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('reference_number', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('department'), fn ($q, $v) => $q->where('department_id', $v))
            ->when($request->query('project'), fn ($q, $v) => $q->where('project_id', $v))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    /**
     * Chronological record of the request from creation to delivery.
     *
     * @return Collection<int, array{label: string, date: mixed, by: ?string, note: ?string}>
     */
    private function timeline(ProcurementRequest $pr): Collection
    {
        $events = collect([[
            'label' => __('Request created'), 'date' => $pr->created_at, 'by' => $pr->requester?->name, 'note' => null,
        ]]);

        foreach ($pr->approvals()->with(['requester', 'decider'])->get() as $approval) {
            /** @var Approval $approval */
            $events->push(['label' => __('Submitted for approval'), 'date' => $approval->created_at, 'by' => $approval->requester?->name, 'note' => $approval->request_note]);
            if ($approval->decided_at) {
                $events->push([
                    'label' => match ($approval->status) {
                        'approved' => __('Approved'),
                        'rejected' => __('Rejected'),
                        default => __('Approval request withdrawn'),
                    },
                    'date' => $approval->decided_at, 'by' => $approval->decider?->name, 'note' => $approval->decision_comment,
                ]);
            }
        }

        foreach ($pr->quotations as $quotation) {
            $events->push(['label' => __('Quotation received from :supplier', ['supplier' => $quotation->supplier?->name]), 'date' => $quotation->created_at, 'by' => null, 'note' => money($quotation->amount, $quotation->currency)]);
        }

        foreach ($pr->purchaseOrders as $po) {
            $events->push(['label' => __('Purchase order :number issued', ['number' => $po->po_number]), 'date' => $po->created_at, 'by' => null, 'note' => $po->supplier?->name]);
            foreach ($po->receipts as $receipt) {
                /** @var GoodsReceipt $receipt */
                $events->push(['label' => __('Goods received'), 'date' => $receipt->created_at, 'by' => $receipt->receiver?->name, 'note' => $receipt->delivery_note_number]);
            }
            if ($po->status === 'cancelled') {
                $events->push(['label' => __('Purchase order :number cancelled', ['number' => $po->po_number]), 'date' => $po->updated_at, 'by' => null, 'note' => null]);
            }
        }

        $cancellation = AuditLog::query()->with('user')
            ->where('auditable_type', $pr->getMorphClass())->where('auditable_id', $pr->id)
            ->where('event', 'cancelled')->latest('created_at')->first();
        if ($cancellation) {
            $events->push(['label' => __('Request cancelled'), 'date' => $cancellation->created_at, 'by' => $cancellation->user?->name, 'note' => $cancellation->description]);
        }

        return $events->sortBy(fn ($e) => $e['date']?->getTimestamp() ?? 0)->values();
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'justification' => ['nullable', 'string', 'max:10000'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'budget_id' => ['nullable', Rule::exists('budgets', 'id')->whereNull('deleted_at')],
            'needed_by' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.specifications' => ['nullable', 'string', 'max:2000'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.estimated_unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
        ], [
            'items.required' => __('Add at least one item.'),
        ], [
            'items.*.description' => __('item description'),
            'items.*.quantity' => __('quantity'),
            'items.*.estimated_unit_cost' => __('estimated unit cost'),
        ]);

        $items = collect($data['items'])->values()->map(fn (array $item) => [
            'description' => $item['description'],
            'specifications' => $item['specifications'] ?? null,
            'quantity' => $item['quantity'],
            'unit' => filled($item['unit'] ?? null) ? $item['unit'] : 'unit',
            'estimated_unit_cost' => $item['estimated_unit_cost'],
        ])->all();

        unset($data['items']);
        $data['currency'] = strtoupper($data['currency']);

        return [$data, $items];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(ProcurementRequest $pr): array
    {
        $budgets = Budget::options();
        if ($pr->budget_id && ! isset($budgets[$pr->budget_id]) && $pr->budget) {
            $budgets[$pr->budget_id] = $pr->budget->code.' — '.$pr->budget->name;
        }

        return [
            'pr' => $pr,
            'departments' => Department::options(),
            'projects' => Project::options(),
            'budgets' => $budgets,
        ];
    }
}
