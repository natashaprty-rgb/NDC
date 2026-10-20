<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\ProcurementRequestItem;
use App\Models\PurchaseOrder;
use App\Support\Audit;
use App\Support\FileStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Goods received against a purchase order. Quantities can never exceed what
 * is still outstanding; order and request statuses follow automatically.
 */
class GoodsReceiptController extends Controller
{
    public function store(Request $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('receive', $purchaseOrder);

        $data = $request->validate([
            'received_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$purchaseOrder->order_date->toDateString()],
            'delivery_note_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'file' => FileStore::rules(),
        ]);

        $quantities = collect($data['quantities'])
            ->map(fn ($qty) => round((float) $qty, 2))
            ->filter(fn (float $qty) => $qty > 0);

        if ($quantities->isEmpty()) {
            throw ValidationException::withMessages(['quantities' => __('Enter the quantity received for at least one item.')]);
        }

        $receipt = DB::transaction(function () use ($purchaseOrder, $data, $quantities, $request) {
            $items = ProcurementRequestItem::query()
                ->where('procurement_request_id', $purchaseOrder->procurement_request_id)
                ->whereKey($quantities->keys()->all())
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];
            foreach ($quantities as $itemId => $qty) {
                $item = $items->get($itemId);
                if ($item === null) {
                    $errors["quantities.{$itemId}"] = __('This item does not belong to the order.');
                } elseif ($qty - $item->outstandingQuantity() > 0.004) {
                    $errors["quantities.{$itemId}"] = __('Only :qty of “:item” is outstanding.', [
                        'qty' => rtrim(rtrim(number_format($item->outstandingQuantity(), 2, '.', ''), '0'), '.'),
                        'item' => $item->description,
                    ]);
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }

            /** @var GoodsReceipt $receipt */
            $receipt = $purchaseOrder->receipts()->create([
                'received_on' => $data['received_on'],
                'received_by' => $request->user()->id,
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($quantities as $itemId => $qty) {
                $receipt->items()->create(['procurement_request_item_id' => $itemId, 'quantity' => $qty]);
                $item = $items->get($itemId);
                $item->forceFill(['quantity_received' => round((float) $item->quantity_received + $qty, 2)])->save();
            }

            if ($request->hasFile('file')) {
                FileStore::attach($receipt, $request->file('file'), 'delivery_note');
            }

            $purchaseOrder->syncDeliveryStatus(Carbon::parse($data['received_on']));
            $purchaseOrder->request->syncReceiptStatus();

            return $receipt;
        });

        Audit::log('goods_received', $purchaseOrder->request, $purchaseOrder->po_number.($receipt->delivery_note_number ? ' · '.$receipt->delivery_note_number : ''));

        return redirect()->to(route('purchase-orders.show', $purchaseOrder).'#receipts')->with('success', __('Delivery recorded.'));
    }
}
