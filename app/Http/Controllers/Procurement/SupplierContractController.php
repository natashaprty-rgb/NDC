<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\SupplierContractRequest;
use App\Models\Supplier;
use App\Models\SupplierContract;
use App\Support\ReferenceNumber;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierContractController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SupplierContract::class);
        $days = (int) Settings::get('notifications.expiry_days_before', 30);

        $contracts = SupplierContract::query()
            ->with('supplier')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('reference', 'like', $term)
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term)));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->boolean('expiring'), fn ($q) => $q->whereIn('status', ['draft', 'active'])
                ->whereNotNull('end_date')->whereBetween('end_date', [today(), today()->addDays($days)]))
            ->orderByRaw('end_date IS NULL')->orderBy('end_date')
            ->paginate(25)
            ->withQueryString();

        return view('contracts.index', compact('contracts', 'days'));
    }

    public function create(Supplier $supplier): View
    {
        $this->authorize('create', SupplierContract::class);

        return view('contracts.form', [
            'supplier' => $supplier,
            'contract' => new SupplierContract(['status' => 'active', 'currency' => Settings::get('format.currency', 'USD')]),
        ]);
    }

    public function store(SupplierContractRequest $request, Supplier $supplier): RedirectResponse
    {
        $contract = DB::transaction(fn () => $supplier->contracts()->create($request->validated() + [
            'reference' => ReferenceNumber::next('contract'),
        ]));

        return redirect()->route('contracts.show', $contract)->with('success', __('common.created'));
    }

    public function show(SupplierContract $contract): View
    {
        $this->authorize('view', $contract);
        $contract->load('supplier');

        return view('contracts.show', compact('contract'));
    }

    public function edit(SupplierContract $contract): View
    {
        $this->authorize('update', $contract);

        return view('contracts.form', ['contract' => $contract, 'supplier' => $contract->supplier]);
    }

    public function update(SupplierContractRequest $request, SupplierContract $contract): RedirectResponse
    {
        $contract->update($request->validated());

        return redirect()->route('contracts.show', $contract)->with('success', __('common.updated'));
    }

    public function destroy(SupplierContract $contract): RedirectResponse
    {
        $this->authorize('delete', $contract);
        $supplier = $contract->supplier;
        $contract->delete();

        return redirect()->route('suppliers.show', $supplier)->with('success', __('common.archived'));
    }
}
