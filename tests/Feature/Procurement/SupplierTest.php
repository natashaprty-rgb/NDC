<?php

namespace Tests\Feature\Procurement;

use App\Models\Category;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    public function test_officer_registers_updates_and_deletes_supplier(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer');
        $category = Category::query()->create(['type' => 'supplier', 'name' => 'ICT equipment']);

        $this->actingAs($officer)->post(route('suppliers.store'), [
            'name' => 'Hormuud Tech Supplies', 'type' => 'supplier', 'category_id' => $category->id,
            'registration_number' => 'REG-1', 'tax_number' => 'TIN-9', 'email' => 'sales@example.test',
            'website' => 'https://example.test', 'status' => 'active',
        ])->assertRedirect();

        $supplier = Supplier::query()->where('name', 'Hormuud Tech Supplies')->firstOrFail();
        $this->assertSame($officer->id, $supplier->created_by);

        $this->actingAs($officer)->post(route('suppliers.store'), ['name' => 'Bad', 'type' => 'vendor', 'status' => 'active', 'website' => 'javascript:alert(1)'])
            ->assertSessionHasErrors(['type', 'website']);

        $this->actingAs($officer)->put(route('suppliers.update', $supplier), [
            'name' => 'Hormuud Tech', 'type' => 'contractor', 'status' => 'suspended',
        ])->assertRedirect(route('suppliers.show', $supplier));
        $this->assertSame('suspended', $supplier->refresh()->status);
        $this->assertArrayNotHasKey($supplier->id, Supplier::options(), 'Suspended suppliers are not offered for new orders.');

        $this->actingAs($officer)->delete(route('suppliers.destroy', $supplier))->assertRedirect(route('suppliers.index'));
        $this->assertSoftDeleted($supplier);
    }

    public function test_supplier_with_orders_cannot_be_deleted(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer');
        $order = PurchaseOrder::factory()->create();

        $this->actingAs($officer)->delete(route('suppliers.destroy', $order->supplier))->assertSessionHas('error');
        $this->assertNotSoftDeleted($order->supplier);
    }

    public function test_evaluations_produce_average_rating(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer');
        $order = PurchaseOrder::factory()->create();
        $supplier = $order->supplier;
        $otherOrder = PurchaseOrder::factory()->create();

        $this->actingAs($officer)->post(route('suppliers.evaluations.store', $supplier), [
            'purchase_order_id' => $order->id, 'quality_rating' => 5, 'delivery_rating' => 4, 'communication_rating' => 3, 'comments' => 'On time',
        ])->assertSessionHasNoErrors();
        $this->actingAs($officer)->post(route('suppliers.evaluations.store', $supplier), [
            'quality_rating' => 2, 'delivery_rating' => 2, 'communication_rating' => 2,
        ])->assertSessionHasNoErrors();

        $this->actingAs($officer)->post(route('suppliers.evaluations.store', $supplier), [
            'purchase_order_id' => $otherOrder->id, 'quality_rating' => 6, 'delivery_rating' => 0, 'communication_rating' => 3,
        ])->assertSessionHasErrors(['purchase_order_id', 'quality_rating', 'delivery_rating']);

        // (4 + 2) / 2 = 3.0
        $this->assertSame(3.0, $supplier->averageRating());
        $this->assertSame(2, $supplier->evaluations()->count());

        $this->actingAs($officer)->get(route('suppliers.show', $supplier))->assertOk()->assertSee('3.0 / 5')->assertSee('On time');
        $this->actingAs($officer)->get(route('suppliers.index'))->assertOk()->assertSee('3.0 / 5');
    }

    public function test_procurement_viewers_can_read_but_not_manage_suppliers(): void
    {
        $auditor = $this->userWithRole('Read-Only Auditor');
        $supplier = Supplier::factory()->create();

        $this->actingAs($auditor)->get(route('suppliers.index'))->assertOk();
        $this->actingAs($auditor)->get(route('suppliers.show', $supplier))->assertOk();
        $this->actingAs($auditor)->get(route('suppliers.report'))->assertOk()->assertSee($supplier->name);
        $this->actingAs($auditor)->get(route('suppliers.create'))->assertForbidden();
        $this->actingAs($auditor)->post(route('suppliers.evaluations.store', $supplier), [
            'quality_rating' => 5, 'delivery_rating' => 5, 'communication_rating' => 5,
        ])->assertForbidden();

        $this->actingAs($this->userWithRole('General Staff'))->get(route('suppliers.index'))->assertForbidden();
    }

    public function test_supplier_pages_render(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer');
        $supplier = Supplier::factory()->create();

        foreach ([route('suppliers.index'), route('suppliers.create'), route('suppliers.show', $supplier), route('suppliers.edit', $supplier), route('suppliers.report')] as $url) {
            $this->actingAs($officer)->get($url)->assertOk();
        }
    }
}
