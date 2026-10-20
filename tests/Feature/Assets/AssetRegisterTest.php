<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetRegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
    }

    public function test_officer_registers_asset_with_generated_tag_and_specifications(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer');
        $category = Category::query()->create(['type' => 'asset', 'name' => 'Servers']);
        $department = Department::query()->create(['name' => 'ICT', 'code' => 'ICT']);
        $supplier = Supplier::factory()->create();

        $this->actingAs($officer)->post(route('assets.store'), [
            'name' => 'Rack server',
            'category_id' => $category->id,
            'manufacturer' => 'Acme',
            'model' => 'R-100',
            'serial_number' => 'SN-001',
            'supplier_id' => $supplier->id,
            'purchase_date' => '2026-01-10',
            'purchase_cost' => '4500.50',
            'currency' => 'usd',
            'warranty_expiry' => '2029-01-10',
            'location' => 'Data hall A',
            'department_id' => $department->id,
            'condition' => 'new',
            'specifications' => [
                ['key' => 'Rack position', 'value' => 'R2-U14'],
                ['key' => 'Power draw', 'value' => '650 W'],
                ['key' => '', 'value' => 'ignored'],
            ],
        ])->assertRedirect();

        $asset = Asset::query()->firstOrFail();
        $this->assertSame('NDC/AST/'.now()->year.'/0001', $asset->asset_tag);
        $this->assertSame('available', $asset->status);
        $this->assertSame('USD', $asset->currency);
        // MySQL's JSON type may reorder keys, so compare contents only.
        $this->assertEquals(['Rack position' => 'R2-U14', 'Power draw' => '650 W'], $asset->specifications);
        $this->assertSame($officer->id, $asset->created_by);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'asset', 'auditable_id' => $asset->id]);

        $this->actingAs($officer)->get(route('assets.show', $asset))
            ->assertOk()->assertSee('R2-U14')->assertSee('Rack position');
    }

    public function test_tags_are_unique_and_generated_tags_skip_manual_ones(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer');
        $base = ['currency' => 'USD', 'condition' => 'good'];
        $tag = 'NDC/AST/'.now()->year.'/0001';

        $this->actingAs($officer)->post(route('assets.store'), $base + ['name' => 'Manual', 'asset_tag' => $tag])->assertRedirect();
        $this->actingAs($officer)->post(route('assets.store'), $base + ['name' => 'Duplicate', 'asset_tag' => $tag])
            ->assertSessionHasErrors('asset_tag');
        $this->actingAs($officer)->post(route('assets.store'), $base + ['name' => 'Auto'])->assertRedirect();

        $this->assertSame(2, Asset::query()->count());
        $this->assertSame('NDC/AST/'.now()->year.'/0002', Asset::query()->where('name', 'Auto')->value('asset_tag'));
    }

    public function test_officer_updates_and_deletes_asset(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer');
        $admin = $this->superAdmin();
        $asset = Asset::factory()->create(['specifications' => ['RAM' => '16 GB']]);

        $this->actingAs($officer)->put(route('assets.update', $asset), [
            'name' => 'Renamed laptop', 'asset_tag' => $asset->asset_tag, 'currency' => 'USD', 'condition' => 'damaged',
            'specifications' => [['key' => 'RAM', 'value' => '32 GB']],
        ])->assertRedirect(route('assets.show', $asset));

        $asset->refresh();
        $this->assertSame('Renamed laptop', $asset->name);
        $this->assertSame(['RAM' => '32 GB'], $asset->specifications);
        $this->assertSame('damaged', $asset->status, 'An available asset recorded as damaged is shown as damaged.');

        $this->actingAs($officer)->delete(route('assets.destroy', $asset))->assertForbidden();
        $this->actingAs($admin)->delete(route('assets.destroy', $asset))->assertRedirect(route('assets.index'));
        $this->assertSoftDeleted($asset);
    }

    public function test_general_staff_can_view_but_not_manage_assets(): void
    {
        $staff = $this->userWithRole('General Staff');
        $asset = Asset::factory()->create();

        $this->actingAs($staff)->get(route('assets.index'))->assertOk()->assertDontSee(route('assets.create'));
        $this->actingAs($staff)->get(route('assets.show', $asset))->assertOk();
        $this->actingAs($staff)->get(route('assets.create'))->assertForbidden();
        $this->actingAs($staff)->post(route('assets.store'), ['name' => 'X', 'currency' => 'USD', 'condition' => 'good'])->assertForbidden();
        $this->actingAs($staff)->get(route('assets.edit', $asset))->assertForbidden();
        $this->actingAs($staff)->post(route('assets.assign', $asset), ['user_id' => $staff->id, 'assigned_on' => today()->toDateString()])->assertForbidden();
        $this->actingAs($staff)->post(route('attachments.store'), [
            'attachable_type' => 'asset', 'attachable_id' => $asset->id, 'file' => UploadedFile::fake()->create('invoice.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_users_without_asset_permission_are_refused(): void
    {
        $user = $this->userWithPermissions(['dashboard.view']);

        $this->actingAs($user)->get(route('assets.index'))->assertForbidden();
    }

    public function test_register_filters_by_status_and_warranty(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer');
        Asset::factory()->create(['name' => 'Expiring printer', 'warranty_expiry' => today()->addDays(10)->toDateString()]);
        Asset::factory()->create(['name' => 'Long warranty switch', 'warranty_expiry' => today()->addYears(2)->toDateString()]);
        Asset::factory()->create(['name' => 'Old desk', 'warranty_expiry' => today()->subYear()->toDateString(), 'status' => 'retired']);

        $this->actingAs($officer)->get(route('assets.index', ['warranty' => '30']))
            ->assertOk()->assertSee('Expiring printer')->assertDontSee('Long warranty switch')->assertDontSee('Old desk');
        $this->actingAs($officer)->get(route('assets.index', ['warranty' => 'expired']))
            ->assertSee('Old desk')->assertDontSee('Expiring printer');
        $this->actingAs($officer)->get(route('assets.index', ['status' => 'retired', 'q' => 'desk']))
            ->assertSee('Old desk')->assertDontSee('Long warranty switch');

        $asset = Asset::query()->where('name', 'Expiring printer')->first();
        $this->assertTrue($asset->isUnderWarranty());
        $this->assertTrue($asset->warrantyExpiresWithin(30));
    }

    public function test_asset_pages_and_reports_render(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer');
        $category = Category::query()->create(['type' => 'asset', 'name' => 'Laptops']);
        $asset = Asset::factory()->create(['category_id' => $category->id]);
        $holder = $this->userWithRole('General Staff');
        $this->actingAs($officer)->post(route('assets.assign', $asset), ['user_id' => $holder->id, 'assigned_on' => today()->toDateString()]);

        foreach ([
            route('assets.index'), route('assets.create'), route('assets.show', $asset), route('assets.edit', $asset),
            route('assets.reports.inventory'), route('assets.reports.allocation'),
        ] as $url) {
            $this->actingAs($officer)->get($url)->assertOk();
        }

        $this->actingAs($officer)->get(route('assets.reports.inventory'))->assertSee('Laptops')->assertSee($asset->asset_tag)->assertSee('data-print', false);
        $this->actingAs($officer)->get(route('assets.reports.allocation'))->assertSee($holder->name)->assertSee($asset->asset_tag);
    }

    public function test_asset_pages_render_in_somali(): void
    {
        $officer = $this->userWithRole('Finance or Procurement Officer', ['locale' => 'so']);
        $asset = Asset::factory()->create(['specifications' => ['RAM' => '16 GB']]);

        $this->actingAs($officer)->get(route('assets.index'))->assertOk()->assertSee('Diiwaanka hantida');
        $this->actingAs($officer)->get(route('assets.show', $asset))->assertOk()->assertSee('Faahfaahinta farsamada');
        $this->actingAs($officer)->get(route('assets.create'))->assertOk();
        $this->actingAs($officer)->get(route('assets.reports.inventory'))->assertOk()->assertSee('Warbixinta kaydka hantida');
    }
}
