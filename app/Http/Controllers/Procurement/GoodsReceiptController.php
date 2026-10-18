<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Http\Requests\Procurement\GoodsReceiptRequest;
use App\Models\PurchaseOrder;
use App\Services\Procurement\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;

class GoodsReceiptController extends Controller
{
    public function store(GoodsReceiptRequest $request, PurchaseOrder $purchaseOrder, PurchaseOrderService $service): RedirectResponse
    {
        $service->receive($purchaseOrder, $request->user(), $request->safe()->except(['items', 'file']), $request->validated('items'), $request->file('file'));

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', __('procurement.receipt_recorded'));
    }
}
