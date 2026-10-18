<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\Department;
use App\Models\Staff;
use Database\Factories\LocationFactory;
use Tests\TestCase;

class AssetTest extends TestCase
{
    private function category(): AssetCategory
    {
        return AssetCategory::query()->firstOrCreate(['code' => 'CMP'], ['name' => 'Computers', 'asset_class' => 'ict']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Dell Latitude 5440',
            'category_id' => $this->category()->id,
            'condition' => 'new',
            'currency' => 'usd',
            'serial_number' => 'SN-001',
            'purchase_cost' => '1234.56',
            'purchase_date' => today()->subMonth()->toDateString(),
        ], $overrides);
    }

    public function test_officer_registers_asset_with_generated_tag_and_exact_cost(): void
    {
        $officer = $this->userWithRole('finance-officer');

        $this->actingAs($officer)->post('/assets', $this->payload([
            'specifications' => [['key' => 'RAM', 'value' => '16 GB'], ['key' => '', 'value' => '']],
        ]))->assertRedirect();

        $asset = Asset::query()->where('serial_number', 'SN-001')->firstOrFail();
        $this->assertStringStartsWith('NDC-AST-', $asset->asset_tag);
        $this->assertSame('1234.56', $asset->purchase_cost);
        $this->assertSame('USD', $asset->currency);
        $this->assertSame('in_stock', $asset->status);
        $this->assertSame([['key' => 'RAM', 'value' => '16 GB']], $asset->specifications);
        $this->assertSame($officer->id, $asset->created_by);
    }

    public function test_duplicate_serial_and_tag_and_invalid_cost_are_rejected(): void
    {
        $officer = $this->userWithRole('finance-officer');
        Asset::factory()->create(['serial_number' => 'SN-001', 'asset_tag' => 'TAG-1']);

        $this->actingAs($officer)->post('/assets', $this->payload())->assertSessionHasErrors('serial_number');
        $this->actingAs($officer)->post('/assets', $this->payload(['serial_number' => 'SN-2', 'asset_tag' => 'TAG-1']))->assertSessionHasErrors('asset_tag');
        $this->actingAs($officer)->post('/assets', $this->payload(['serial_number' => 'SN-3', 'purchase_cost' => '10.999']))->assertSessionHasErrors('purchase_cost');
        $this->actingAs($officer)->post('/assets', $this->payload(['serial_number' => 'SN-4', 'purchase_date' => today()->addDay()->toDateString()]))->assertSessionHasErrors('purchase_date');
        $this->assertSame(1, Asset::query()->count());
    }

    public function test_generated_tags_are_unique(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $this->actingAs($officer)->post('/assets', $this->payload(['serial_number' => 'A']));
        $this->actingAs($officer)->post('/assets', $this->payload(['serial_number' => 'B', 'category_id' => AssetCategory::query()->first()->id]));

        $tags = Asset::query()->pluck('asset_tag');
        $this->assertCount(2, $tags->unique());
    }

    public function test_assign_then_transfer_records_history_and_updates_holder(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $first = Staff::factory()->create(['department_id' => $deptA->id]);
        $second = Staff::factory()->create(['department_id' => $deptB->id]);
        $location = LocationFactory::new()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($officer)->post("/assets/{$asset->id}/movements", [
            'action' => 'assigned', 'to_staff_id' => $first->id, 'to_location_id' => $location->id, 'movement_date' => today()->toDateString(),
        ])->assertRedirect();
        $asset->refresh();
        $this->assertSame($first->id, $asset->assigned_staff_id);
        $this->assertSame('in_use', $asset->status);
        $this->assertSame($deptA->id, $asset->department_id);

        // Assigning again is refused: must transfer.
        $this->actingAs($officer)->post("/assets/{$asset->id}/movements", ['action' => 'assigned', 'to_staff_id' => $second->id, 'movement_date' => today()->toDateString()])
            ->assertSessionHasErrors('action');

        $this->actingAs($officer)->post("/assets/{$asset->id}/movements", [
            'action' => 'transferred', 'to_staff_id' => $second->id, 'movement_date' => today()->toDateString(), 'notes' => 'Reassigned',
        ])->assertRedirect();
        $asset->refresh();
        $this->assertSame($second->id, $asset->assigned_staff_id);
        $this->assertSame($deptB->id, $asset->department_id);

        $movements = AssetMovement::query()->where('asset_id', $asset->id)->orderBy('id')->get();
        $this->assertCount(2, $movements);
        $this->assertSame(['assigned', 'transferred'], $movements->pluck('action')->all());
        $this->assertSame($first->id, $movements[1]->from_staff_id);
        $this->assertSame($second->id, $movements[1]->to_staff_id);
        $this->assertSame($officer->id, $movements[1]->performed_by);

        $this->actingAs($officer)->post("/assets/{$asset->id}/movements", ['action' => 'returned', 'movement_date' => today()->toDateString()])->assertRedirect();
        $this->assertNull($asset->fresh()->assigned_staff_id);
        $this->assertSame('in_stock', $asset->fresh()->status);
    }

    public function test_retired_asset_cannot_be_assigned_or_edited(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $staff = Staff::factory()->create();
        $asset = Asset::factory()->create();

        $this->actingAs($officer)->post("/assets/{$asset->id}/retire", ['status' => 'disposed', 'retired_on' => today()->toDateString()])
            ->assertSessionHasErrors('retirement_reason');
        $this->actingAs($officer)->post("/assets/{$asset->id}/retire", ['status' => 'disposed', 'retired_on' => today()->toDateString(), 'retirement_reason' => 'Beyond repair'])
            ->assertRedirect();
        $this->assertSame('disposed', $asset->fresh()->status);
        $this->assertTrue(AssetMovement::query()->where('asset_id', $asset->id)->where('action', 'retired')->exists());

        $this->actingAs($officer)->post("/assets/{$asset->id}/movements", ['action' => 'assigned', 'to_staff_id' => $staff->id, 'movement_date' => today()->toDateString()])
            ->assertForbidden();
        $this->actingAs($officer)->get("/assets/{$asset->id}/edit")->assertForbidden();
        $this->actingAs($officer)->get("/assets/{$asset->id}")->assertOk()->assertSee('Beyond repair');
    }

    public function test_maintenance_status_drives_asset_status(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $staff = Staff::factory()->create();
        $asset = Asset::factory()->create(['status' => 'in_use', 'assigned_staff_id' => $staff->id]);

        $this->actingAs($officer)->post("/assets/{$asset->id}/maintenance", [
            'type' => 'corrective', 'description' => 'Replace battery', 'status' => 'in_progress', 'cost' => '80.10',
        ])->assertRedirect();
        $this->assertSame('under_maintenance', $asset->fresh()->status);
        $record = $asset->maintenances()->firstOrFail();
        $this->assertSame('80.10', $record->cost);

        $this->actingAs($officer)->post("/assets/{$asset->id}/movements", ['action' => 'returned', 'movement_date' => today()->toDateString()])
            ->assertSessionHasErrors('action');

        $this->actingAs($officer)->put("/assets/{$asset->id}/maintenance/{$record->id}", [
            'type' => 'corrective', 'description' => 'Replace battery', 'status' => 'completed', 'cost' => '80.10',
        ])->assertRedirect();
        $this->assertSame('in_use', $asset->fresh()->status);
        $this->assertNotNull($record->fresh()->completed_date);

        $this->actingAs($officer)->post("/assets/{$asset->id}/maintenance", ['type' => 'inspection', 'description' => 'Check', 'status' => 'completed', 'cost' => '19.90']);
        $this->actingAs($officer)->get("/assets/{$asset->id}")->assertOk()->assertSee('100.00');
    }

    public function test_maintenance_record_of_another_asset_cannot_be_updated(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $a = Asset::factory()->create();
        $b = Asset::factory()->create();
        $record = $b->maintenances()->create(['type' => 'inspection', 'description' => 'x', 'status' => 'scheduled']);

        $this->actingAs($officer)->put("/assets/{$a->id}/maintenance/{$record->id}", ['type' => 'inspection', 'description' => 'x', 'status' => 'completed'])
            ->assertNotFound();
    }

    public function test_staff_can_view_but_not_manage_assets(): void
    {
        $staff = $this->userWithRole('staff');
        $viewer = $this->userWithRole('project-officer');
        $asset = Asset::factory()->create();

        $this->actingAs($staff)->get('/assets')->assertForbidden(); // general staff role has no assets.view
        $this->actingAs($viewer)->get('/assets')->assertOk()->assertSee($asset->name);
        $this->actingAs($viewer)->get("/assets/{$asset->id}")->assertOk();
        $this->actingAs($viewer)->get('/assets/create')->assertForbidden();
        $this->actingAs($viewer)->post('/assets', $this->payload())->assertForbidden();
        $this->actingAs($viewer)->post("/assets/{$asset->id}/maintenance", ['type' => 'inspection', 'description' => 'x', 'status' => 'scheduled'])->assertForbidden();
        $this->actingAs($viewer)->delete("/assets/{$asset->id}")->assertForbidden();
        $this->assertSame(0, Asset::query()->where('name', 'Dell Latitude 5440')->count());
    }

    public function test_filters_restrict_results(): void
    {
        $viewer = $this->userWithRole('project-officer');
        Asset::factory()->create(['name' => 'Printer HP', 'status' => 'in_stock', 'manufacturer' => 'HP']);
        Asset::factory()->create(['name' => 'Laptop Lenovo', 'status' => 'in_use', 'warranty_expiry' => today()->addDays(20)]);
        Asset::factory()->create(['name' => 'Old desk', 'status' => 'retired', 'warranty_expiry' => today()->addDays(10)]);

        $this->actingAs($viewer)->get('/assets?status=in_use')->assertSee('Laptop Lenovo')->assertDontSee('Printer HP');
        $this->actingAs($viewer)->get('/assets?q=HP')->assertSee('Printer HP')->assertDontSee('Laptop Lenovo');
        $this->actingAs($viewer)->get('/assets?warranty_expiring=1')->assertSee('Laptop Lenovo')->assertDontSee('Printer HP')->assertDontSee('Old desk');
        $this->actingAs($viewer)->get('/assets?status=bogus')->assertSessionHasErrors('status');
    }

    public function test_forms_render_for_manager(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $asset = Asset::factory()->create(['specifications' => [['key' => 'CPU', 'value' => 'i7']]]);

        $this->actingAs($officer)->get('/assets/create')->assertOk();
        $this->actingAs($officer)->get("/assets/{$asset->id}/edit")->assertOk()->assertSee('i7');
        $this->actingAs($officer)->get("/assets/{$asset->id}")->assertOk()->assertSee('CPU');
    }
}
