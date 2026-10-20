<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Supplier;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Suppliers, contractors and consultants.
 */
class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $query = self::filteredQuery($request)->with('category')
            ->withCount(['purchaseOrders', 'evaluations'])
            ->withAvg('evaluations as quality_avg', 'quality_rating')
            ->withAvg('evaluations as delivery_avg', 'delivery_rating')
            ->withAvg('evaluations as communication_avg', 'communication_rating');
        $this->applySort($query, $request, ['name', 'type', 'status', 'created_at'], 'name', 'asc');

        return view('suppliers.index', [
            'suppliers' => $query->paginate($this->perPage($request))->withQueryString(),
            'categories' => Category::options('supplier'),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.form', $this->formData(new Supplier(['type' => 'supplier', 'status' => 'active'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = Supplier::query()->create([...$this->validated($request), 'created_by' => $request->user()->id]);

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Supplier registered.'));
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        $supplier->load(['category', 'attachments.uploader', 'evaluations.evaluator', 'evaluations.purchaseOrder']);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'average' => $supplier->averageRating(),
            'criteria' => [
                __('Quality') => $supplier->evaluations->avg('quality_rating'),
                __('Delivery') => $supplier->evaluations->avg('delivery_rating'),
                __('Communication') => $supplier->evaluations->avg('communication_rating'),
            ],
            'orders' => $supplier->purchaseOrders()->with('request')->latest('order_date')->limit(25)->get(),
            'quotations' => $supplier->quotations()->with('request')->latest()->limit(25)->get(),
            'orderOptions' => $supplier->purchaseOrders()->orderByDesc('order_date')->pluck('po_number', 'id'),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.form', $this->formData($supplier));
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('suppliers.show', $supplier)->with('success', __('Supplier updated.'));
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        if ($supplier->purchaseOrders()->exists() || $supplier->quotations()->exists()) {
            return back()->with('error', __('This supplier has quotations or purchase orders. Mark it inactive instead.'));
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', __('Supplier deleted.'));
    }

    public static function filteredQuery(Request $request): Builder
    {
        return Supplier::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")
                ->orWhere('contact_person', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('registration_number', 'like', "%{$term}%")
                ->orWhere('tax_number', 'like', "%{$term}%")))
            ->when($request->query('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->query('category'), fn ($q, $v) => $q->where('category_id', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Status::keys('supplier_type'))],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('type', 'supplier')],
            'registration_number' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'status' => ['required', Rule::in(Status::keys('supplier'))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Supplier $supplier): array
    {
        return [
            'supplier' => $supplier,
            'categories' => Category::options('supplier', $supplier->category_id),
        ];
    }
}
