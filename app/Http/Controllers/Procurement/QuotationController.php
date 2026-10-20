<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ProcurementRequest;
use App\Models\Quotation;
use App\Support\Audit;
use App\Support\FileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Supplier quotations recorded against an approved purchase request. One
 * quotation per request may be marked as selected.
 */
class QuotationController extends Controller
{
    public function store(Request $request, ProcurementRequest $procurementRequest): RedirectResponse
    {
        $this->authorize('manage', $procurementRequest);

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'received_on' => ['nullable', 'date', 'before_or_equal:today'],
            'valid_until' => array_filter(['nullable', 'date', $request->filled('received_on') ? 'after_or_equal:received_on' : null]),
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => FileStore::rules(),
        ]);

        DB::transaction(function () use ($procurementRequest, $data, $request) {
            /** @var Quotation $quotation */
            $quotation = $procurementRequest->quotations()->create([
                ...collect($data)->except('file')->all(),
                'currency' => strtoupper($data['currency']),
                'is_selected' => false,
            ]);

            if ($request->hasFile('file')) {
                FileStore::attach($quotation, $request->file('file'), 'quotation');
            }
        });

        return redirect()->to(route('procurement.show', $procurementRequest).'#quotations')->with('success', __('Quotation recorded.'));
    }

    /**
     * Mark one quotation as the selected offer; any previous selection is cleared.
     */
    public function select(ProcurementRequest $procurementRequest, Quotation $quotation): RedirectResponse
    {
        $this->authorize('manage', $procurementRequest);

        DB::transaction(function () use ($procurementRequest, $quotation) {
            $procurementRequest->quotations()->where('id', '!=', $quotation->id)->where('is_selected', true)
                ->get()->each(fn (Quotation $q) => $q->update(['is_selected' => false]));
            $quotation->update(['is_selected' => true]);
        });

        Audit::log('quotation_selected', $procurementRequest, $quotation->supplier?->name.' — '.money($quotation->amount, $quotation->currency));

        return redirect()->to(route('procurement.show', $procurementRequest).'#quotations')->with('success', __('Quotation selected.'));
    }

    public function destroy(ProcurementRequest $procurementRequest, Quotation $quotation): RedirectResponse
    {
        $this->authorize('manage', $procurementRequest);

        if ($quotation->purchaseOrders()->exists()) {
            return back()->with('error', __('This quotation is linked to a purchase order and cannot be removed.'));
        }

        $quotation->delete();

        return redirect()->to(route('procurement.show', $procurementRequest).'#quotations')->with('success', __('Quotation removed.'));
    }
}
