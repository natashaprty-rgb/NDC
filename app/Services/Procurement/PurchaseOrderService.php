<?php

namespace App\Services\Procurement;

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FileStorage;
use App\Support\Money;
use App\Support\ReferenceNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    /** Quantity (in hundredths) of each PR item already on non-cancelled purchase orders. */
    public function orderedHundredths(PurchaseRequest $pr): array
    {
        $ordered = [];
        $rows = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->where('purchase_request_id', $pr->id)->where('status', '!=', 'cancelled'))
            ->whereNotNull('purchase_request_item_id')
            ->get(['purchase_request_item_id', 'quantity']);
        foreach ($rows as $row) {
            $ordered[$row->purchase_request_item_id] = ($ordered[$row->purchase_request_item_id] ?? 0) + Money::toCents((string) $row->quantity);
        }

        return $ordered;
    }

    /** Select one quotation; any other quotation of the request is unselected. */
    public function selectQuotation(Quotation $quotation): void
    {
        DB::transaction(function () use ($quotation) {
            PurchaseRequest::query()->lockForUpdate()->findOrFail($quotation->purchase_request_id);
            Quotation::query()->where('purchase_request_id', $quotation->purchase_request_id)->update(['is_selected' => false]);
            $quotation->forceFill(['is_selected' => true])->save();
        });
    }

    /**
     * @param  array<int, array{purchase_request_item_id:int|string, quantity:string|null, unit_price:string}>  $items
     */
    public function create(PurchaseRequest $pr, array $data, array $items): PurchaseOrder
    {
        return DB::transaction(function () use ($pr, $data, $items) {
            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($pr->id);
            if (! in_array($pr->status, ['approved', 'ordered', 'partially_received'], true)) {
                throw ValidationException::withMessages(['items' => __('procurement.pr_not_approved')]);
            }

            $prItems = PurchaseRequestItem::query()->where('purchase_request_id', $pr->id)->get()->keyBy('id');
            $ordered = $this->orderedHundredths($pr);
            $lines = [];
            foreach ($items as $i => $item) {
                $qty = Money::toCents((string) ($item['quantity'] ?? '0'));
                if ($qty <= 0) {
                    continue;
                }
                $prItem = $prItems->get((int) $item['purchase_request_item_id']);
                $remaining = Money::toCents((string) $prItem->quantity) - ($ordered[$prItem->id] ?? 0);
                if ($qty > $remaining) {
                    throw ValidationException::withMessages([
                        "items.$i.quantity" => __('procurement.qty_exceeds_request', ['max' => Money::fromCents(max(0, $remaining))]),
                    ]);
                }
                $ordered[$prItem->id] = ($ordered[$prItem->id] ?? 0) + $qty;
                $quantity = Money::fromCents($qty);
                $price = Money::fromCents(Money::toCents((string) $item['unit_price']));
                $lines[] = [
                    'purchase_request_item_id' => $prItem->id,
                    'description' => $prItem->description,
                    'quantity' => $quantity,
                    'unit' => $prItem->unit,
                    'unit_price' => $price,
                    'line_total' => Money::multiply($quantity, $price),
                ];
            }

            if ($lines === []) {
                throw ValidationException::withMessages(['items' => __('procurement.no_quantities')]);
            }

            $po = PurchaseOrder::query()->create([
                'reference' => ReferenceNumber::next('purchase_order'),
                'purchase_request_id' => $pr->id,
                'supplier_id' => $data['supplier_id'],
                'quotation_id' => $data['quotation_id'] ?? null,
                'order_date' => $data['order_date'],
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'currency' => $pr->currency,
                'total_amount' => Money::sum(array_column($lines, 'line_total')),
                'status' => 'issued',
                'terms' => $data['terms'] ?? null,
            ]);
            $po->items()->createMany($lines);

            if ($pr->status === 'approved') {
                $pr->update(['status' => 'ordered']);
            }

            return $po;
        });
    }

    public function cancel(PurchaseOrder $po): void
    {
        DB::transaction(function () use ($po) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            abort_unless($po->status === 'issued' && ! $po->receipts()->exists(), 409);
            $po->update(['status' => 'cancelled']);
            AuditLogger::log('cancelled', $po, 'Purchase order cancelled');

            $pr = PurchaseRequest::query()->lockForUpdate()->find($po->purchase_request_id);
            $activeOrders = PurchaseOrder::query()->where('purchase_request_id', $po->purchase_request_id)->where('status', '!=', 'cancelled')->exists();
            if ($pr && ! $activeOrders && $pr->status === 'ordered') {
                $pr->update(['status' => 'approved']);
            }
        });
    }

    /**
     * Record goods received against a purchase order.
     *
     * @param  array<int, array{purchase_order_item_id:int|string, quantity_received:string|null, condition_note?:string|null}>  $items
     */
    public function receive(PurchaseOrder $po, User $user, array $data, array $items, ?UploadedFile $file): GoodsReceipt
    {
        $receipt = DB::transaction(function () use ($po, $user, $data, $items) {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($po->id);
            if (! in_array($po->status, ['issued', 'partially_delivered'], true)) {
                throw ValidationException::withMessages(['items' => __('procurement.po_not_receivable')]);
            }
            $poItems = PurchaseOrderItem::query()->where('purchase_order_id', $po->id)->lockForUpdate()->get()->keyBy('id');

            $lines = [];
            foreach ($items as $i => $item) {
                $qty = Money::toCents((string) ($item['quantity_received'] ?? '0'));
                if ($qty <= 0) {
                    continue;
                }
                $poItem = $poItems->get((int) $item['purchase_order_item_id']);
                if ($qty > $poItem->outstandingHundredths()) {
                    throw ValidationException::withMessages([
                        "items.$i.quantity_received" => __('procurement.qty_exceeds_outstanding', ['max' => Money::fromCents($poItem->outstandingHundredths())]),
                    ]);
                }
                $lines[] = [$poItem, $qty, $item['condition_note'] ?? null];
            }
            if ($lines === []) {
                throw ValidationException::withMessages(['items' => __('procurement.no_quantities')]);
            }

            $receipt = GoodsReceipt::query()->create([
                'reference' => ReferenceNumber::next('goods_receipt'),
                'purchase_order_id' => $po->id,
                'received_date' => $data['received_date'],
                'received_by' => $user->id,
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as [$poItem, $qty, $note]) {
                $receipt->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'quantity_received' => Money::fromCents($qty),
                    'condition_note' => $note,
                ]);
                $poItem->update(['quantity_received' => Money::fromCents(Money::toCents((string) $poItem->quantity_received) + $qty)]);
            }

            $complete = $poItems->every(fn (PurchaseOrderItem $item) => $item->fresh()->outstandingHundredths() <= 0);
            $po->update(['status' => $complete ? 'delivered' : 'partially_delivered']);

            $pr = PurchaseRequest::query()->lockForUpdate()->findOrFail($po->purchase_request_id);
            $allOrders = PurchaseOrder::query()->where('purchase_request_id', $pr->id)->where('status', '!=', 'cancelled')->get();
            $orderedAll = collect($this->orderedHundredths($pr));
            $requestedAll = $pr->items()->get()->every(fn ($i) => ($orderedAll[$i->id] ?? 0) >= Money::toCents((string) $i->quantity));
            $prReceived = $requestedAll && $allOrders->every(fn ($o) => $o->status === 'delivered');
            $pr->update(['status' => $prReceived ? 'received' : 'partially_received']);

            AuditLogger::log('received', $po, 'Goods received: '.$receipt->reference, [], ['goods_receipt_id' => $receipt->id]);

            return $receipt;
        });

        if ($file) {
            FileStorage::attach($receipt, $file, 'delivery_note');
        }

        return $receipt;
    }
}
