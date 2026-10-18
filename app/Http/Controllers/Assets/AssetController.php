<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assets\AssetRequest;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Department;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Staff;
use App\Models\Supplier;
use App\Services\Assets\AssetService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssetController extends Controller
{
    public function __construct(private readonly AssetService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Asset::class);
        $request->validate([
            'status' => ['nullable', Rule::in(Asset::STATUSES)],
            'condition' => ['nullable', Rule::in(Asset::CONDITIONS)],
        ]);

        $assets = Asset::query()
            ->with(['category', 'location', 'department', 'assignedStaff'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('asset_tag', 'like', $term)->orWhere('name', 'like', $term)
                    ->orWhere('serial_number', 'like', $term)->orWhere('model', 'like', $term)->orWhere('manufacturer', 'like', $term));
            })
            ->when($request->filled('category'), fn ($q) => $q->where('category_id', (int) $request->query('category')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('condition'), fn ($q) => $q->where('condition', $request->query('condition')))
            ->when($request->filled('location'), fn ($q) => $q->where('location_id', (int) $request->query('location')))
            ->when($request->filled('department'), fn ($q) => $q->where('department_id', (int) $request->query('department')))
            ->when($request->filled('staff'), fn ($q) => $q->where('assigned_staff_id', (int) $request->query('staff')))
            ->when($request->boolean('warranty_expiring'), fn ($q) => $q->whereNotIn('status', Asset::RETIRED_STATUSES)
                ->whereNotNull('warranty_expiry')->whereBetween('warranty_expiry', [today(), today()->addDays(60)]))
            ->orderBy('asset_tag')
            ->paginate(25)
            ->withQueryString();

        $totals = Asset::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status')->map(fn ($v) => (int) $v);

        return view('assets.index', ['assets' => $assets, 'totals' => $totals] + $this->filterOptions());
    }

    public function create(): View
    {
        $this->authorize('create', Asset::class);

        return view('assets.form', ['asset' => new Asset(['condition' => 'new', 'currency' => 'USD'])] + $this->formOptions());
    }

    public function store(AssetRequest $request): RedirectResponse
    {
        $asset = $this->service->register($request->validated(), $request->user());

        return redirect()->route('assets.show', $asset)->with('success', __('common.created'));
    }

    public function show(Asset $asset): View
    {
        $this->authorize('view', $asset);
        $asset->load(['category', 'supplier', 'purchaseOrder', 'location', 'department', 'assignedStaff',
            'movements.fromStaff', 'movements.toStaff', 'movements.fromLocation', 'movements.toLocation', 'movements.toDepartment', 'movements.performer',
            'maintenances.supplier']);

        return view('assets.show', [
            'asset' => $asset,
            'maintenanceTotal' => Money::sum($asset->maintenances->where('status', '!=', 'cancelled')->pluck('cost')->filter()),
        ] + $this->formOptions());
    }

    public function edit(Asset $asset): View
    {
        $this->authorize('update', $asset);

        return view('assets.form', ['asset' => $asset] + $this->formOptions($asset));
    }

    public function update(AssetRequest $request, Asset $asset): RedirectResponse
    {
        $this->service->update($asset, $request->validated());

        return redirect()->route('assets.show', $asset)->with('success', __('common.updated'));
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);
        $asset->delete();

        return redirect()->route('assets.index')->with('success', __('common.archived'));
    }

    private function filterOptions(): array
    {
        return [
            'categories' => AssetCategory::query()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all(),
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => Department::query()->orderBy('name')->pluck('name', 'id')->all(),
            'staffList' => Staff::query()->orderBy('full_name')->pluck('full_name', 'id')->all(),
        ];
    }

    private function formOptions(?Asset $asset = null): array
    {
        return [
            'categories' => AssetCategory::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $asset?->category_id ?? 0))
                ->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => Department::query()->active()->orderBy('name')->pluck('name', 'id')->all(),
            'staffList' => Staff::query()->where('employment_status', '!=', 'separated')->orderBy('full_name')->pluck('full_name', 'id')->all(),
            'suppliers' => Supplier::query()->orderBy('name')->pluck('name', 'id')->all(),
            'purchaseOrders' => PurchaseOrder::query()->orderByDesc('id')->limit(500)->pluck('reference', 'id')->all(),
        ];
    }
}
