<?php

namespace Tests\Feature\Procurement;

use App\Models\ProcurementRequest;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcurementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;

    private User $officer;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        $this->requester = $this->userWithRole('Project Officer');
        $this->officer = $this->userWithRole('Finance or Procurement Officer');
        $this->director = $this->userWithRole('Director or Management');
    }

    private function createRequest(): ProcurementRequest
    {
        $this->actingAs($this->requester)->post(route('procurement.store'), [
            'title' => 'Network equipment for data hall',
            'justification' => 'Expansion of core network',
            'needed_by' => today()->addMonth()->toDateString(),
            'currency' => 'USD',
            'items' => [
                ['description' => 'Core switch', 'quantity' => '2', 'unit' => 'unit', 'estimated_unit_cost' => '1500.00'],
                ['description' => 'Fibre patch cable', 'specifications' => 'OM4, 3 m', 'quantity' => '10', 'unit' => '', 'estimated_unit_cost' => '12.50'],
            ],
        ])->assertRedirect();

        return ProcurementRequest::query()->latest('id')->firstOrFail();
    }

    private function approvedRequest(): ProcurementRequest
    {
        $pr = $this->createRequest();
        $this->actingAs($this->requester)->post(route('procurement.submit', $pr));
        $this->actingAs($this->director)->post(route('approvals.decide', $pr->approvals()->first()), ['decision' => 'approve']);

        return $pr->refresh();
    }

    public function test_request_with_items_computes_total_and_reference(): void
    {
        $pr = $this->createRequest();

        $this->assertSame('NDC/PR/'.now()->year.'/0001', $pr->reference_number);
        $this->assertSame('draft', $pr->status);
        $this->assertSame('3125.00', $pr->estimated_total);
        $this->assertSame($this->requester->id, $pr->requested_by);
        $this->assertSame(2, $pr->items()->count());
        $this->assertSame('unit', $pr->items()->where('description', 'Fibre patch cable')->value('unit'));

        $this->actingAs($this->requester)->post(route('procurement.store'), ['title' => 'Empty', 'currency' => 'USD', 'items' => []])
            ->assertSessionHasErrors('items');

        // Editing a draft replaces the items and recalculates.
        $this->actingAs($this->requester)->put(route('procurement.update', $pr), [
            'title' => 'Network equipment', 'currency' => 'USD',
            'items' => [['description' => 'Core switch', 'quantity' => '3', 'estimated_unit_cost' => '1000']],
        ])->assertRedirect(route('procurement.show', $pr));
        $this->assertSame('3000.00', $pr->refresh()->estimated_total);
        $this->assertSame(1, $pr->items()->count());
    }

    public function test_submit_requires_approval_by_a_different_user_and_locks_editing(): void
    {
        $pr = $this->createRequest();

        $this->actingAs($this->requester)->post(route('procurement.submit', $pr), ['note' => 'Urgent'])->assertSessionHasNoErrors();
        $pr->refresh();
        $this->assertSame('submitted', $pr->status);
        $this->assertNotNull($pr->submitted_at);
        $approval = $pr->approvals()->firstOrFail();
        $this->assertSame('procurement.approve', $approval->permission);
        Notification::assertSentTo($this->director, ApprovalRequested::class);

        // Locked once submitted.
        $this->actingAs($this->requester)->get(route('procurement.edit', $pr))->assertForbidden();
        $this->actingAs($this->requester)->put(route('procurement.update', $pr), [
            'title' => 'Changed', 'currency' => 'USD', 'items' => [['description' => 'X', 'quantity' => 1, 'estimated_unit_cost' => 1]],
        ])->assertForbidden();
        $this->actingAs($this->requester)->post(route('procurement.submit', $pr))->assertForbidden();

        // Requester cannot approve their own request even with the permission.
        $this->requester->givePermissionTo('procurement.approve');
        $this->actingAs($this->requester)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('submitted', $pr->refresh()->status);

        $this->actingAs($this->director)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $pr->refresh()->status);
    }

    public function test_rejected_request_can_be_edited_and_resubmitted(): void
    {
        $pr = $this->createRequest();
        $this->actingAs($this->requester)->post(route('procurement.submit', $pr));
        $this->actingAs($this->director)->post(route('approvals.decide', $pr->approvals()->first()), ['decision' => 'reject', 'decision_comment' => 'Get more quotes']);

        $this->assertSame('rejected', $pr->refresh()->status);
        $this->actingAs($this->requester)->get(route('procurement.edit', $pr))->assertOk();
        $this->actingAs($this->requester)->post(route('procurement.submit', $pr))->assertSessionHasNoErrors();
        $this->assertSame('submitted', $pr->refresh()->status);
        $this->assertSame(1, $pr->approvals()->where('status', 'pending')->count());
    }

    public function test_quotations_and_orders_need_an_approved_request(): void
    {
        $pr = $this->createRequest();
        $supplier = Supplier::factory()->create();

        $this->actingAs($this->officer)->get(route('purchase-orders.create', $pr))->assertForbidden();
        $this->actingAs($this->officer)->post(route('purchase-orders.store', $pr), [
            'supplier_id' => $supplier->id, 'order_date' => today()->toDateString(), 'total_amount' => 100, 'currency' => 'USD',
        ])->assertForbidden();
        $this->actingAs($this->officer)->post(route('procurement.quotations.store', $pr), [
            'supplier_id' => $supplier->id, 'amount' => 100, 'currency' => 'USD',
        ])->assertForbidden();

        $this->actingAs($this->requester)->post(route('procurement.submit', $pr));
        $this->actingAs($this->officer)->get(route('purchase-orders.create', $pr))->assertForbidden();
        $this->assertSame(0, PurchaseOrder::query()->count());
    }

    public function test_only_one_quotation_can_be_selected(): void
    {
        $pr = $this->approvedRequest();
        [$a, $b] = Supplier::factory()->count(2)->create();

        $this->actingAs($this->officer)->post(route('procurement.quotations.store', $pr), [
            'supplier_id' => $a->id, 'reference' => 'Q-A', 'amount' => '3000', 'currency' => 'usd', 'received_on' => today()->toDateString(),
            'file' => UploadedFile::fake()->create('quote-a.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->officer)->post(route('procurement.quotations.store', $pr), [
            'supplier_id' => $b->id, 'reference' => 'Q-B', 'amount' => '2900', 'currency' => 'USD',
        ])->assertSessionHasNoErrors();

        $qa = Quotation::query()->where('reference', 'Q-A')->firstOrFail();
        $qb = Quotation::query()->where('reference', 'Q-B')->firstOrFail();
        $this->assertSame(1, $qa->attachments()->count());
        $this->assertSame('USD', $qa->currency);

        $this->actingAs($this->officer)->post(route('procurement.quotations.select', [$pr, $qa]))->assertRedirect();
        $this->actingAs($this->officer)->post(route('procurement.quotations.select', [$pr, $qb]))->assertRedirect();

        $this->assertFalse($qa->refresh()->is_selected);
        $this->assertTrue($qb->refresh()->is_selected);
        $this->assertSame(1, $pr->quotations()->where('is_selected', true)->count());

        // A quotation from another request cannot be selected through this one.
        $other = Quotation::factory()->create();
        $this->actingAs($this->officer)->post(route('procurement.quotations.select', [$pr, $other]))->assertNotFound();

        // Requester (no procurement.update) cannot manage quotations.
        $this->actingAs($this->requester)->post(route('procurement.quotations.select', [$pr, $qa]))->assertForbidden();
    }

    public function test_purchase_order_defaults_to_selected_quotation_and_marks_request_ordered(): void
    {
        $pr = $this->approvedRequest();
        $supplier = Supplier::factory()->create(['name' => 'Selected Vendor Ltd']);
        $quotation = $pr->quotations()->create(['supplier_id' => $supplier->id, 'amount' => 2950, 'currency' => 'USD', 'is_selected' => true]);

        $this->actingAs($this->officer)->get(route('purchase-orders.create', $pr))
            ->assertOk()->assertSee('Selected Vendor Ltd')->assertSee('2950.00');

        $wrongSupplier = Supplier::factory()->create();
        $this->actingAs($this->officer)->post(route('purchase-orders.store', $pr), [
            'supplier_id' => $wrongSupplier->id, 'quotation_id' => $quotation->id, 'order_date' => today()->toDateString(), 'total_amount' => 2950, 'currency' => 'USD',
        ])->assertSessionHasErrors('quotation_id');

        $this->actingAs($this->officer)->post(route('purchase-orders.store', $pr), [
            'supplier_id' => $supplier->id, 'quotation_id' => $quotation->id, 'order_date' => today()->toDateString(),
            'expected_delivery_date' => today()->addWeeks(2)->toDateString(), 'total_amount' => '2950', 'currency' => 'USD',
            'contract_reference' => 'CTR-77', 'contract_end_date' => today()->addYear()->toDateString(), 'terms' => 'Net 30',
        ])->assertRedirect();

        $order = PurchaseOrder::query()->firstOrFail();
        $this->assertSame('NDC/PO/'.now()->year.'/0001', $order->po_number);
        $this->assertSame('issued', $order->status);
        $this->assertSame($this->officer->id, $order->created_by);
        $this->assertSame('ordered', $pr->refresh()->status);

        // Only one order per approved request.
        $this->actingAs($this->officer)->get(route('purchase-orders.create', $pr))->assertForbidden();

        // Cancelling the order before delivery returns the request to approved.
        $this->actingAs($this->officer)->post(route('purchase-orders.cancel', $order), ['reason' => 'Supplier withdrew'])->assertRedirect();
        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertSame('approved', $pr->refresh()->status);
    }

    public function test_goods_receipts_update_statuses_and_refuse_over_receipt(): void
    {
        $pr = $this->approvedRequest();
        $supplier = Supplier::factory()->create();
        $this->actingAs($this->officer)->post(route('purchase-orders.store', $pr), [
            'supplier_id' => $supplier->id, 'order_date' => today()->subDays(5)->toDateString(), 'total_amount' => 3125, 'currency' => 'USD',
        ]);
        $order = PurchaseOrder::query()->firstOrFail();
        $switch = $pr->items()->where('description', 'Core switch')->firstOrFail();
        $cable = $pr->items()->where('description', 'Fibre patch cable')->firstOrFail();

        // Over-receipt is refused and nothing is recorded.
        $this->actingAs($this->officer)->post(route('purchase-orders.receipts.store', $order), [
            'received_on' => today()->toDateString(), 'quantities' => [$switch->id => 3],
        ])->assertSessionHasErrors('quantities.'.$switch->id);
        $this->actingAs($this->officer)->post(route('purchase-orders.receipts.store', $order), [
            'received_on' => today()->toDateString(), 'quantities' => [$switch->id => 0],
        ])->assertSessionHasErrors('quantities');
        $this->assertSame(0, $order->receipts()->count());

        // Partial delivery.
        $this->actingAs($this->officer)->post(route('purchase-orders.receipts.store', $order), [
            'received_on' => today()->subDay()->toDateString(), 'delivery_note_number' => 'DN-1',
            'quantities' => [$switch->id => 2, $cable->id => 4],
            'file' => UploadedFile::fake()->create('dn-1.pdf', 15, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame('2.00', $switch->refresh()->quantity_received);
        $this->assertSame('4.00', $cable->refresh()->quantity_received);
        $this->assertSame('partially_delivered', $order->refresh()->status);
        $this->assertNull($order->delivered_on);
        $this->assertSame('partially_received', $pr->refresh()->status);
        $this->assertSame(1, $order->receipts()->first()->attachments()->count());

        // Remaining cable can be received, but not more than outstanding.
        $this->actingAs($this->officer)->post(route('purchase-orders.receipts.store', $order), [
            'received_on' => today()->toDateString(), 'quantities' => [$cable->id => 7],
        ])->assertSessionHasErrors('quantities.'.$cable->id);

        $this->actingAs($this->officer)->post(route('purchase-orders.receipts.store', $order), [
            'received_on' => today()->toDateString(), 'delivery_note_number' => 'DN-2', 'quantities' => [$cable->id => 6],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame(today()->toDateString(), $order->delivered_on->toDateString());
        $this->assertSame('received', $pr->refresh()->status);
        $this->assertSame(2, $order->receipts()->count());

        // Fully delivered orders accept no more receipts and the request cannot be cancelled.
        $this->actingAs($this->officer)->post(route('purchase-orders.receipts.store', $order), [
            'received_on' => today()->toDateString(), 'quantities' => [$cable->id => 1],
        ])->assertForbidden();
        $this->actingAs($this->officer)->post(route('procurement.cancel', $pr), ['reason' => 'Late'])->assertForbidden();

        $this->actingAs($this->officer)->get(route('procurement.show', $pr))
            ->assertOk()->assertSee(__('Goods received'))->assertSee($order->po_number);
    }

    public function test_request_can_be_cancelled_before_delivery(): void
    {
        $pr = $this->createRequest();
        $this->actingAs($this->requester)->post(route('procurement.submit', $pr));

        $this->actingAs($this->requester)->post(route('procurement.cancel', $pr), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->officer)->post(route('procurement.cancel', $pr), ['reason' => 'No longer required'])->assertRedirect();

        $this->assertSame('cancelled', $pr->refresh()->status);
        $this->assertSame('cancelled', $pr->approvals()->first()->status, 'The pending approval is withdrawn.');
    }

    public function test_general_staff_cannot_access_procurement(): void
    {
        $staff = $this->userWithRole('General Staff');

        $this->actingAs($staff)->get(route('procurement.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('purchase-orders.index'))->assertForbidden();
    }

    public function test_auditor_can_view_but_not_create_requests(): void
    {
        $auditor = $this->userWithRole('Read-Only Auditor');
        $pr = $this->createRequest();

        $this->actingAs($auditor)->get(route('procurement.show', $pr))->assertOk();
        $this->actingAs($auditor)->get(route('procurement.create'))->assertForbidden();
        $this->actingAs($auditor)->post(route('procurement.submit', $pr))->assertForbidden();
    }

    public function test_procurement_pages_render(): void
    {
        $pr = $this->approvedRequest();
        $supplier = Supplier::factory()->create();
        $pr->quotations()->create(['supplier_id' => $supplier->id, 'amount' => 3000, 'currency' => 'USD', 'is_selected' => true]);
        $this->actingAs($this->officer)->post(route('purchase-orders.store', $pr), [
            'supplier_id' => $supplier->id, 'order_date' => today()->toDateString(), 'total_amount' => 3000, 'currency' => 'USD',
        ]);
        $order = PurchaseOrder::query()->firstOrFail();
        $draft = $this->createRequest();

        foreach ([
            route('procurement.index'), route('procurement.create'), route('procurement.show', $pr), route('procurement.edit', $draft),
            route('procurement.register'), route('purchase-orders.index'), route('purchase-orders.show', $order),
            route('purchase-orders.edit', $order),
        ] as $url) {
            $this->actingAs($this->officer)->get($url)->assertOk();
        }

        $this->actingAs($this->officer)->get(route('procurement.register'))->assertSee($pr->reference_number)->assertSee($order->po_number);
        $this->actingAs($this->officer)->get(route('procurement.show', $pr))->assertSee(__('Approved'));
    }

    public function test_procurement_pages_render_in_somali(): void
    {
        $this->requester->update(['locale' => 'so']);
        $pr = $this->createRequest();

        $this->actingAs($this->requester)->get(route('procurement.index'))->assertOk()->assertSee('Codsiyada iibsiga');
        $this->actingAs($this->requester)->get(route('procurement.show', $pr))->assertOk()->assertSee('Faahfaahinta codsiga')->assertSee('Qabyo');
        $this->actingAs($this->requester)->get(route('suppliers.index'))->assertOk()->assertSee('Alaab-qeybiyeyaasha iyo qandaraaslayaasha');
    }
}
