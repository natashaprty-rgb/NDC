<?php

namespace Tests\Feature\Procurement;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierContract;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    public function test_finance_officer_registers_supplier_with_generated_code_and_edits_it(): void
    {
        $officer = $this->userWithRole('finance-officer');

        $this->actingAs($officer)->post('/suppliers', ['name' => 'Acme Office Supplies', 'status' => 'active', 'email' => 'sales@acme.test'])
            ->assertRedirect();
        $supplier = Supplier::query()->where('name', 'Acme Office Supplies')->firstOrFail();
        $this->assertMatchesRegularExpression('/^NDC-SUP-\d{4}-0001$/', $supplier->code);
        $this->assertSame($officer->id, $supplier->created_by);

        $this->actingAs($officer)->put("/suppliers/{$supplier->id}", ['code' => $supplier->code, 'name' => 'Acme Ltd', 'status' => 'inactive'])
            ->assertRedirect(route('suppliers.show', $supplier));
        $this->assertSame('inactive', $supplier->fresh()->status);
    }

    public function test_duplicate_code_and_invalid_status_are_rejected(): void
    {
        $officer = $this->userWithRole('finance-officer');
        Supplier::factory()->create(['code' => 'SUP-001']);

        $this->actingAs($officer)->post('/suppliers', ['code' => 'SUP-001', 'name' => 'Other', 'status' => 'active'])->assertSessionHasErrors('code');
        $this->actingAs($officer)->post('/suppliers', ['name' => 'Other', 'status' => 'bogus'])->assertSessionHasErrors('status');
        $this->assertSame(1, Supplier::query()->count());
    }

    public function test_view_only_and_unauthorised_users_cannot_modify_suppliers(): void
    {
        $director = $this->userWithRole('director'); // suppliers.view only
        $staff = $this->userWithRole('staff');       // no suppliers.view
        $supplier = Supplier::factory()->create();

        $this->actingAs($director)->get('/suppliers')->assertOk()->assertSee($supplier->name);
        $this->actingAs($director)->post('/suppliers', ['name' => 'X', 'status' => 'active'])->assertForbidden();
        $this->actingAs($director)->delete("/suppliers/{$supplier->id}")->assertForbidden();
        $this->actingAs($staff)->get('/suppliers')->assertForbidden();
        $this->actingAs($staff)->get("/suppliers/{$supplier->id}")->assertForbidden();
    }

    public function test_supplier_with_open_purchase_orders_cannot_be_archived(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $supplier = Supplier::factory()->create();
        PurchaseOrder::factory()->create(['supplier_id' => $supplier->id, 'status' => 'issued']);

        $this->actingAs($officer)->delete("/suppliers/{$supplier->id}")->assertSessionHas('error');
        $this->assertNotSoftDeleted($supplier);

        $free = Supplier::factory()->create();
        $this->actingAs($officer)->delete("/suppliers/{$free->id}")->assertRedirect(route('suppliers.index'));
        $this->assertSoftDeleted($free);
    }

    public function test_contract_is_created_with_reference_and_validated(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $supplier = Supplier::factory()->create();

        $this->actingAs($officer)->post("/suppliers/{$supplier->id}/contracts", [
            'title' => 'Printer maintenance', 'start_date' => '2026-01-01', 'end_date' => '2025-01-01',
            'contract_value' => '100.00', 'currency' => 'USD', 'status' => 'active',
        ])->assertSessionHasErrors('end_date');

        $this->actingAs($officer)->post("/suppliers/{$supplier->id}/contracts", [
            'title' => 'Printer maintenance', 'start_date' => '2026-01-01', 'end_date' => '2027-01-01',
            'contract_value' => '1200.505', 'currency' => 'USD', 'status' => 'active',
        ])->assertSessionHasErrors('contract_value');

        $this->actingAs($officer)->post("/suppliers/{$supplier->id}/contracts", [
            'title' => 'Printer maintenance', 'start_date' => '2026-01-01', 'end_date' => '2027-01-01',
            'contract_value' => '1200.50', 'currency' => 'USD', 'status' => 'active',
        ])->assertRedirect();

        $contract = SupplierContract::query()->firstOrFail();
        $this->assertStringStartsWith('NDC-CTR-', $contract->reference);
        $this->assertSame('1200.50', $contract->contract_value);
    }

    public function test_expiring_filter_lists_only_contracts_ending_soon(): void
    {
        $officer = $this->userWithRole('finance-officer');
        SupplierContract::factory()->create(['title' => 'Ends soon', 'end_date' => today()->addDays(10)]);
        SupplierContract::factory()->create(['title' => 'Ends next year', 'end_date' => today()->addYear()]);
        SupplierContract::factory()->create(['title' => 'Already ended', 'end_date' => today()->subDay()]);

        $this->actingAs($officer)->get('/contracts?expiring=1')
            ->assertOk()->assertSee('Ends soon')->assertDontSee('Ends next year')->assertDontSee('Already ended');
        $this->actingAs($officer)->get('/contracts')->assertSee('Ends next year');
    }
}
