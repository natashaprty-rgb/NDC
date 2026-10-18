<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\PurchaseRequestRequest;
use App\Models\Budget;
use App\Models\Department;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Policies\PurchaseRequestPolicy;
use App\Services\Procurement\PurchaseOrderService;
use App\Services\Procurement\PurchaseRequestService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    public function __construct(private readonly PurchaseRequestService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PurchaseRequest::class);
        $user = $request->user();

        $query = PurchaseRequest::query()->with(['requester', 'department', 'project']);
        if (! PurchaseRequestPolicy::hasOversight($user)) {
            $headed = $user->headedDepartmentIds();
            $query->where(function ($q) use ($user, $headed) {
                $q->where('requested_by', $user->id);
                if ($headed !== []) {
                    $q->orWhereIn('department_id', $headed);
                }
            });
        }

        $requests = $query
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('reference', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', $request->integer('department')))
            ->when($request->filled('project'), fn ($q) => $q->where('project_id', $request->integer('project')))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('purchase-requests.index', [
            'requests' => $requests,
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id')->all(),
            'projects' => Project::query()->notArchived()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', PurchaseRequest::class);

        return view('purchase-requests.form', [
            'pr' => new PurchaseRequest([
                'currency' => Settings::get('format.currency', 'USD'),
                'department_id' => $request->user()->departmentId(),
                'project_id' => $request->integer('project') ?: null,
            ]),
            'items' => [['description' => '', 'quantity' => '1', 'unit' => 'unit', 'estimated_unit_cost' => '']],
        ] + $this->formOptions());
    }

    public function store(PurchaseRequestRequest $request): RedirectResponse
    {
        $pr = $this->service->saveDraft(null, $request->validated(), $request->validated('items'), $request->user());

        return redirect()->route('purchase-requests.show', $pr)->with('success', __('procurement.draft_saved'));
    }

    public function show(PurchaseRequest $purchaseRequest, PurchaseOrderService $orders): View
    {
        $this->authorize('view', $purchaseRequest);
        $purchaseRequest->load(['items', 'requester', 'decidedBy', 'department', 'project', 'budget', 'quotations.supplier', 'purchaseOrders.supplier']);

        $canOrder = request()->user()->can('order', $purchaseRequest);

        return view('purchase-requests.show', [
            'pr' => $purchaseRequest,
            'suppliers' => $canOrder ? Supplier::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all() : [],
            'canOrder' => $canOrder,
        ]);
    }

    public function edit(PurchaseRequest $purchaseRequest): View
    {
        $this->authorize('update', $purchaseRequest);

        return view('purchase-requests.form', [
            'pr' => $purchaseRequest,
            'items' => $purchaseRequest->items->map(fn ($i) => $i->only(['description', 'quantity', 'unit', 'estimated_unit_cost']))->all(),
        ] + $this->formOptions());
    }

    public function update(PurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->service->saveDraft($purchaseRequest, $request->validated(), $request->validated('items'), $request->user());

        return redirect()->route('purchase-requests.show', $purchaseRequest)->with('success', __('procurement.draft_saved'));
    }

    public function destroy(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('delete', $purchaseRequest);
        $purchaseRequest->delete();

        return redirect()->route('purchase-requests.index')->with('success', __('common.deleted'));
    }

    public function submit(PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('submit', $purchaseRequest);
        $this->service->submit($purchaseRequest);

        return back()->with('success', __('procurement.submitted_msg'));
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('approve', $purchaseRequest);
        $data = $request->validate(['decision_note' => ['nullable', 'string', 'max:2000']]);
        $this->service->decide($purchaseRequest, $request->user(), true, $data['decision_note'] ?? null);

        return back()->with('success', __('procurement.approved_msg'));
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('approve', $purchaseRequest);
        $data = $request->validate(['decision_note' => ['required', 'string', 'max:2000']], [], ['decision_note' => __('common.decision_note')]);
        $this->service->decide($purchaseRequest, $request->user(), false, $data['decision_note']);

        return back()->with('success', __('procurement.rejected_msg'));
    }

    public function cancel(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $this->authorize('cancel', $purchaseRequest);
        if ($purchaseRequest->purchaseOrders()->where('status', '!=', 'cancelled')->exists()) {
            return back()->with('error', __('procurement.cannot_cancel_ordered'));
        }
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], [], ['reason' => __('common.reason')]);
        $this->service->cancel($purchaseRequest, $data['reason']);

        return back()->with('success', __('procurement.cancelled_msg'));
    }

    private function formOptions(): array
    {
        return [
            'departments' => Department::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'projects' => Project::query()->notArchived()->orderBy('name')->pluck('name', 'id')->all(),
            'budgets' => Budget::query()->where('status', 'approved')->orderByDesc('fiscal_year')->get()
                ->mapWithKeys(fn (Budget $b) => [$b->id => $b->reference.' — '.$b->title])->all(),
        ];
    }
}
