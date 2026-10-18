<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\SupplierRequest;
use App\Models\Supplier;
use App\Support\ReferenceNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = Supplier::query()
            ->withCount(['contracts', 'purchaseOrders'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('code', 'like', $term)
                    ->orWhere('services', 'like', $term)->orWhere('contact_person', 'like', $term));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.form', ['supplier' => new Supplier(['status' => 'active'])]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $supplier = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $data['code'] = ($data['code'] ?? null) ?: ReferenceNumber::next('supplier');

            return Supplier::query()->create($data);
        });

        return redirect()->route('suppliers.show', $supplier)->with('success', __('common.created'));
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'contracts' => $supplier->contracts()->orderByDesc('end_date')->get(),
            'quotations' => $supplier->quotations()->with('purchaseRequest')->latest('id')->limit(20)->get(),
            'orders' => $supplier->purchaseOrders()->latest('id')->limit(20)->get(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()->route('suppliers.show', $supplier)->with('success', __('common.updated'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchaseOrders()->whereIn('status', ['issued', 'partially_delivered'])->exists()) {
            return back()->with('error', __('suppliers.cannot_archive_open_orders'));
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('common.archived'));
    }
}
