<?php

namespace Tests\Feature\Procurement;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
{
    private User $requester;

    private User $approver;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requester = $this->userWithRole('staff');
        $this->approver = $this->userWithRole('director');
        $this->officer = $this->userWithRole('finance-officer');
    }

    private function approvedRequest(string $qty = '10', string $cost = '5.00'): PurchaseRequest
    {
        return PurchaseRequest::factory()->withItem($qty, $cost)->create([
            'requested_by' => $this->requester->id, 'status' => 'approved', 'decided_by' => $this->approver->id, 'decided_at' => now(),
        ]);
    }

    private function raisePo(PurchaseRequest $pr, string $qty, string $price = '5.00', ?Supplier $supplier = null)
    {
        $supplier ??= Supplier::factory()->create();

        return $this->actingAs($this->officer)->post("/purchase-requests/{$pr->id}/orders", [
            'supplier_id' => $supplier->id,
            'order_date' => today()->toDateString(),
            'items' => [['purchase_request_item_id' => $pr->items()->first()->id, 'quantity' => $qty, 'unit_price' => $price]],
        ]);
    }

    public function test_purchase_order_can_only_be_raised_for_an_approved_request(): void
    {
        $draft = PurchaseRequest::factory()->withItem()->create(['requested_by' => $this->requester->id]);
        $this->raisePo($draft, '1')->assertForbidden();

        $pr = $this->approvedRequest('10', '5.00');
        $this->raisePo($pr, '4', '4.99')->assertRedirect();

        $po = PurchaseOrder::query()->with('items')->firstOrFail();
        $this->assertStringStartsWith('NDC-PO-', $po->reference);
        $this->assertSame('19.96', $po->total_amount);
        $this->assertSame('ordered', $pr->fresh()->status);
    }

    public function test_requester_and_staff_cannot_raise_orders(): void
    {
        $pr = $this->approvedRequest();
        $this->actingAs($this->requester)->get("/purchase-requests/{$pr->id}/orders/create")->assertForbidden();
        $this->actingAs($this->approver)->get("/purchase-requests/{$pr->id}/orders/create")->assertForbidden();
    }

    public function test_order_quantity_cannot_exceed_remaining_request_quantity(): void
    {
        $pr = $this->approvedRequest('10');

        $this->raisePo($pr, '10.01')->assertSessionHasErrors('items.0.quantity');
        $this->raisePo($pr, '6')->assertRedirect();
        $this->raisePo($pr, '5')->assertSessionHasErrors('items.0.quantity'); // only 4 remain
        $this->raisePo($pr, '4')->assertRedirect();
        $this->assertSame(2, PurchaseOrder::query()->count());
    }

    public function test_only_one_quotation_is_selected(): void
    {
        $pr = $this->approvedRequest();
        $s1 = Supplier::factory()->create();
        $s2 = Supplier::factory()->create();

        $this->actingAs($this->officer)->post("/purchase-requests/{$pr->id}/quotations", ['supplier_id' => $s1->id, 'total_amount' => '50.00'])->assertRedirect();
        $this->actingAs($this->officer)->post("/purchase-requests/{$pr->id}/quotations", ['supplier_id' => $s2->id, 'total_amount' => '48.00'])->assertRedirect();
        [$q1, $q2] = Quotation::query()->orderBy('id')->get()->all();

        $this->actingAs($this->officer)->post("/quotations/{$q1->id}/select");
        $this->actingAs($this->officer)->post("/quotations/{$q2->id}/select");

        $this->assertFalse($q1->fresh()->is_selected);
        $this->assertTrue($q2->fresh()->is_selected);
        $this->actingAs($this->requester)->post("/quotations/{$q1->id}/select")->assertForbidden();
    }

    public function test_receiver_who_approved_the_request_is_refused(): void
    {
        $approverReceiver = $this->userWithPermissions(['procurement.view', 'procurement.approve', 'procurement.receive']);
        $pr = PurchaseRequest::factory()->withItem('10')->create([
            'requested_by' => $this->requester->id, 'status' => 'approved', 'decided_by' => $approverReceiver->id,
        ]);
        $this->raisePo($pr, '10');
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($approverReceiver)->post("/purchase-orders/{$po->id}/receipts", [
            'received_date' => today()->toDateString(),
            'items' => [['purchase_order_item_id' => $po->items()->first()->id, 'quantity_received' => '10']],
        ])->assertForbidden();
        $this->assertSame('0.00', $po->items()->first()->quantity_received);
    }

    public function test_partial_then_full_receipt_updates_statuses_and_over_receipt_is_rejected(): void
    {
        $pr = $this->approvedRequest('10');
        $this->raisePo($pr, '10');
        $po = PurchaseOrder::query()->firstOrFail();
        $itemId = $po->items()->first()->id;
        $receive = fn (string $qty) => $this->actingAs($this->officer)->post("/purchase-orders/{$po->id}/receipts", [
            'received_date' => today()->toDateString(),
            'items' => [['purchase_order_item_id' => $itemId, 'quantity_received' => $qty]],
        ]);

        $receive('4')->assertRedirect();
        $this->assertSame('partially_delivered', $po->fresh()->status);
        $this->assertSame('partially_received', $pr->fresh()->status);
        $this->assertSame('4.00', $po->items()->first()->quantity_received);

        $receive('6.01')->assertSessionHasErrors('items.0.quantity_received');
        $receive('0')->assertSessionHasErrors('items');
        $this->assertSame('4.00', $po->items()->first()->quantity_received);

        $receive('6')->assertRedirect();
        $this->assertSame('delivered', $po->fresh()->status);
        $this->assertSame('received', $pr->fresh()->status);
        $this->assertSame(2, $po->receipts()->count());
        $this->assertStringStartsWith('NDC-GRN-', $po->receipts()->first()->reference);

        // A delivered order cannot receive more.
        $receive('1')->assertForbidden();
    }

    public function test_cancelling_the_only_order_returns_request_to_approved(): void
    {
        $pr = $this->approvedRequest();
        $this->raisePo($pr, '5');
        $po = PurchaseOrder::query()->firstOrFail();

        $this->actingAs($this->requester)->post("/purchase-orders/{$po->id}/cancel")->assertForbidden();
        $this->actingAs($this->officer)->post("/purchase-orders/{$po->id}/cancel")->assertRedirect();

        $this->assertSame('cancelled', $po->fresh()->status);
        $this->assertSame('approved', $pr->fresh()->status);
        // Cancelled quantities become available again.
        $this->raisePo($pr, '10')->assertRedirect();
    }

    public function test_purchase_order_visibility(): void
    {
        $pr = $this->approvedRequest();
        $this->raisePo($pr, '5');
        $po = PurchaseOrder::query()->firstOrFail();
        $stranger = $this->userWithRole('staff');

        $this->actingAs($this->officer)->get('/purchase-orders')->assertOk()->assertSee($po->reference);
        $this->actingAs($this->requester)->get("/purchase-orders/{$po->id}")->assertOk();
        $this->actingAs($stranger)->get("/purchase-orders/{$po->id}")->assertForbidden();
        $this->actingAs($stranger)->get('/purchase-orders')->assertForbidden();
    }
}
