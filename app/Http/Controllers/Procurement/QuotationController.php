<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\QuotationRequest;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Services\FileStorage;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class QuotationController extends Controller
{
    public function store(QuotationRequest $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $data = $request->safe()->except('file');
        $quotation = DB::transaction(fn () => $purchaseRequest->quotations()->create($data + ['is_selected' => false]));

        if ($request->hasFile('file')) {
            FileStorage::attach($quotation, $request->file('file'), 'quotation');
        }

        return redirect()->route('purchase-requests.show', $purchaseRequest)->withFragment('quotations')->with('success', __('procurement.quotation_added'));
    }

    public function select(Quotation $quotation, PurchaseOrderService $service): RedirectResponse
    {
        $this->authorize('order', $quotation->purchaseRequest);
        $service->selectQuotation($quotation);

        return redirect()->route('purchase-requests.show', $quotation->purchase_request_id)->withFragment('quotations')->with('success', __('procurement.quotation_selected'));
    }
}
