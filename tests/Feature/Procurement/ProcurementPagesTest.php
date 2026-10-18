<?php

namespace Tests\Feature\Procurement;

use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Quotation;
use App\Models\Supplier;
use App\Models\SupplierContract;
use Tests\TestCase;

class ProcurementPagesTest extends TestCase
{
    public function test_all_procurement_pages_render_for_authorised_roles_in_both_languages(): void
    {
        $requester = $this->userWithRole('staff');
        $director = $this->userWithRole('director');
        $officer = $this->userWithRole('finance-officer');
        $supplier = Supplier::factory()->create();
        $contract = SupplierContract::factory()->create(['supplier_id' => $supplier->id]);
        $draft = PurchaseRequest::factory()->withItem()->create(['requested_by' => $requester->id]);
        $submitted = PurchaseRequest::factory()->withItem()->create(['requested_by' => $requester->id, 'status' => 'submitted']);
        $approved = PurchaseRequest::factory()->withItem()->create(['requested_by' => $requester->id, 'status' => 'approved', 'decided_by' => $director->id]);
        Quotation::query()->create(['purchase_request_id' => $approved->id, 'supplier_id' => $supplier->id, 'total_amount' => '40.00', 'is_selected' => true]);
        $po = PurchaseOrder::factory()->create(['purchase_request_id' => $approved->id, 'supplier_id' => $supplier->id]);
        $po->items()->create(['purchase_request_item_id' => $approved->items()->first()->id, 'description' => 'Item', 'quantity' => '2', 'unit' => 'unit', 'unit_price' => '5.00', 'line_total' => '10.00']);

        foreach (['en', 'so'] as $locale) {
            $officer->update(['locale' => $locale]);
            $requester->update(['locale' => $locale]);
            $director->update(['locale' => $locale]);

            foreach (['/suppliers', '/suppliers/create', "/suppliers/{$supplier->id}", "/suppliers/{$supplier->id}/edit", '/contracts', '/contracts?expiring=1',
                "/suppliers/{$supplier->id}/contracts/create", "/contracts/{$contract->id}", "/contracts/{$contract->id}/edit",
                '/purchase-requests', "/purchase-requests/{$approved->id}", "/purchase-requests/{$approved->id}/orders/create",
                '/purchase-orders', "/purchase-orders/{$po->id}"] as $url) {
                $this->actingAs($officer->fresh())->get($url)->assertOk();
            }
            foreach (['/purchase-requests/create', "/purchase-requests/{$draft->id}", "/purchase-requests/{$draft->id}/edit", "/purchase-orders/{$po->id}"] as $url) {
                $this->actingAs($requester->fresh())->get($url)->assertOk();
            }
            $this->actingAs($director->fresh())->get("/purchase-requests/{$submitted->id}")->assertOk()->assertSee(__('common.approve', [], $locale));
        }
    }
}
