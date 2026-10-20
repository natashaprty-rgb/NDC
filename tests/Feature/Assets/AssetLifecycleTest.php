<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\Department;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Notification::fake();
        $this->officer = $this->userWithRole('Finance or Procurement Officer');
    }

    public function test_assign_return_and_transfer_keep_full_history(): void
    {
        $ict = Department::query()->create(['name' => 'ICT', 'code' => 'ICT']);
        $finance = Department::query()->create(['name' => 'Finance', 'code' => 'FIN']);
        $first = $this->userWithRole('General Staff', ['department_id' => $ict->id]);
        $second = $this->userWithRole('General Staff');
        $asset = Asset::factory()->create();

        $this->actingAs($this->officer)->post(route('assets.assign', $asset), [
            'user_id' => $first->id, 'location' => 'Room 4', 'assigned_on' => today()->subDays(10)->toDateString(),
        ])->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame('assigned', $asset->status);
        $this->assertSame($first->id, $asset->assigned_to);
        $this->assertSame($ict->id, $asset->department_id, 'Department defaults to the employee\'s department.');
        $this->assertSame('Room 4', $asset->location);
        Notification::assertSentTo($first, GenericNotification::class);

        // An assigned asset cannot be assigned again.
        $this->actingAs($this->officer)->post(route('assets.assign', $asset), ['user_id' => $second->id, 'assigned_on' => today()->toDateString()])
            ->assertSessionHas('error');

        $this->actingAs($this->officer)->post(route('assets.transfer', $asset), [
            'user_id' => $second->id, 'department_id' => $finance->id, 'location' => 'Finance office',
            'transferred_on' => today()->subDays(3)->toDateString(), 'condition_on_transfer' => 'fair',
        ])->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame($second->id, $asset->assigned_to);
        $this->assertSame($finance->id, $asset->department_id);
        $this->assertSame('fair', $asset->condition);
        $this->assertSame(2, $asset->assignments()->count());
        $this->assertSame(1, $asset->assignments()->whereNull('returned_on')->count());
        $this->assertDatabaseHas('asset_assignments', ['asset_id' => $asset->id, 'user_id' => $first->id, 'condition_on_return' => 'fair']);

        $this->actingAs($this->officer)->post(route('assets.return', $asset), [
            'returned_on' => today()->toDateString(), 'condition_on_return' => 'damaged', 'return_notes' => 'Screen cracked',
        ])->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertNull($asset->assigned_to);
        $this->assertSame('damaged', $asset->status);
        $this->assertSame(0, $asset->assignments()->whereNull('returned_on')->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'transferred', 'auditable_type' => 'asset', 'auditable_id' => $asset->id]);

        $this->actingAs($this->officer)->get(route('assets.show', $asset))
            ->assertOk()->assertSee($first->name)->assertSee($second->name)->assertSee('Screen cracked');
    }

    public function test_assignment_requires_a_holder_and_returns_to_available(): void
    {
        $asset = Asset::factory()->create();
        $department = Department::query()->create(['name' => 'Registry', 'code' => 'REG']);

        $this->actingAs($this->officer)->post(route('assets.assign', $asset), ['assigned_on' => today()->toDateString()])
            ->assertSessionHasErrors(['user_id', 'department_id']);

        $this->actingAs($this->officer)->post(route('assets.assign', $asset), ['department_id' => $department->id, 'assigned_on' => today()->toDateString()])
            ->assertSessionHasNoErrors();
        $this->assertSame('assigned', $asset->refresh()->status);

        $this->actingAs($this->officer)->post(route('assets.return', $asset), ['returned_on' => today()->toDateString(), 'condition_on_return' => 'good']);
        $this->assertSame('available', $asset->refresh()->status);
    }

    public function test_maintenance_lifecycle_changes_asset_status(): void
    {
        $vendor = Supplier::factory()->create();
        $holder = $this->userWithRole('General Staff');
        $asset = Asset::factory()->create();
        $this->actingAs($this->officer)->post(route('assets.assign', $asset), ['user_id' => $holder->id, 'assigned_on' => today()->toDateString()]);

        $this->actingAs($this->officer)->post(route('assets.maintenance.store', $asset), [
            'type' => 'corrective', 'scheduled_date' => today()->toDateString(), 'supplier_id' => $vendor->id, 'description' => 'Replace fan',
        ])->assertSessionHasNoErrors();
        $maintenance = $asset->maintenances()->firstOrFail();
        $this->assertSame('scheduled', $maintenance->status);
        $this->assertSame('assigned', $asset->refresh()->status);

        $this->actingAs($this->officer)->post(route('assets.maintenance.start', [$asset, $maintenance]))->assertSessionHasNoErrors();
        $this->assertSame('in_progress', $maintenance->refresh()->status);
        $this->assertSame('under_maintenance', $asset->refresh()->status);

        $this->actingAs($this->officer)->post(route('assets.maintenance.complete', [$asset, $maintenance]), [
            'completed_date' => today()->toDateString(), 'cost' => '120.00', 'performed_by' => 'Vendor technician',
        ])->assertSessionHasNoErrors();
        $maintenance->refresh();
        $this->assertSame('completed', $maintenance->status);
        $this->assertSame('120.00', $maintenance->cost);
        $this->assertSame('assigned', $asset->refresh()->status, 'Asset returns to its holder after maintenance.');

        // A second job on an unassigned asset is cancelled while in progress.
        $this->actingAs($this->officer)->post(route('assets.return', $asset), ['returned_on' => today()->toDateString(), 'condition_on_return' => 'good']);
        $this->actingAs($this->officer)->post(route('assets.maintenance.store', $asset), ['type' => 'inspection', 'scheduled_date' => today()->toDateString()]);
        $second = $asset->maintenances()->where('type', 'inspection')->firstOrFail();
        $this->actingAs($this->officer)->post(route('assets.maintenance.start', [$asset, $second]));
        $this->assertSame('under_maintenance', $asset->refresh()->status);
        $this->actingAs($this->officer)->post(route('assets.maintenance.cancel', [$asset, $second]));
        $this->assertSame('cancelled', $second->refresh()->status);
        $this->assertSame('available', $asset->refresh()->status);
    }

    public function test_maintenance_routes_are_scoped_to_their_asset(): void
    {
        $asset = Asset::factory()->create();
        $other = Asset::factory()->create();
        $maintenance = $other->maintenances()->create(['type' => 'preventive', 'status' => 'scheduled', 'scheduled_date' => today()]);

        $this->actingAs($this->officer)->post(route('assets.maintenance.start', [$asset, $maintenance]))->assertNotFound();
    }

    public function test_retirement_needs_approval_by_another_authorised_user(): void
    {
        $director = $this->userWithRole('Director or Management');
        $asset = Asset::factory()->create();

        $this->actingAs($this->officer)->post(route('assets.disposals.store', $asset), [
            'action' => 'retire', 'reason' => 'End of service life',
        ])->assertSessionHasNoErrors();

        $disposal = AssetDisposal::query()->firstOrFail();
        $approval = $disposal->approvals()->firstOrFail();
        $this->assertSame('pending', $disposal->status);
        $this->assertSame('assets.approve-disposal', $approval->permission);
        $this->assertSame('available', $asset->refresh()->status, 'Nothing changes before approval.');

        // The requester cannot decide, even if given the approval permission.
        $this->officer->givePermissionTo('assets.approve-disposal');
        $this->actingAs($this->officer)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('pending', $approval->refresh()->status);

        // Users without the permission cannot decide either.
        $this->actingAs($this->userWithRole('General Staff'))->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertForbidden();

        $this->actingAs($director)->get(route('assets.show', $asset))->assertOk()->assertSee(__('Approve'));
        $this->actingAs($director)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertRedirect();

        $this->assertSame('completed', $disposal->refresh()->status);
        $this->assertSame('retired', $asset->refresh()->status);

        // Retired assets cannot be assigned.
        $this->actingAs($this->officer)->post(route('assets.assign', $asset), ['user_id' => $director->id, 'assigned_on' => today()->toDateString()])
            ->assertSessionHas('error');
    }

    public function test_disposal_is_completed_only_after_approval(): void
    {
        $director = $this->userWithRole('Director or Management');
        $asset = Asset::factory()->create();

        $this->actingAs($this->officer)->post(route('assets.disposals.store', $asset), [
            'action' => 'dispose', 'reason' => 'Beyond economic repair', 'method' => 'Auction',
        ]);
        $disposal = AssetDisposal::query()->firstOrFail();

        // Cannot complete before approval, nor open a second request.
        $this->actingAs($this->officer)->post(route('assets.disposals.complete', [$asset, $disposal]), ['completed_on' => today()->toDateString()])
            ->assertForbidden();
        $this->actingAs($this->officer)->post(route('assets.disposals.store', $asset), ['action' => 'retire', 'reason' => 'Again'])
            ->assertSessionHas('error');
        $this->assertSame(1, AssetDisposal::query()->count());

        $this->actingAs($director)->post(route('approvals.decide', $disposal->approvals()->first()), ['decision' => 'approve', 'decision_comment' => 'Agreed']);
        $this->assertSame('approved', $disposal->refresh()->status);
        $this->assertSame('available', $asset->refresh()->status, 'Asset stays in the register until disposal is carried out.');

        $this->actingAs($this->officer)->post(route('assets.disposals.complete', [$asset, $disposal]), [
            'completed_on' => today()->toDateString(), 'proceeds' => '75.00',
        ])->assertSessionHasNoErrors();

        $this->assertSame('completed', $disposal->refresh()->status);
        $this->assertSame('75.00', $disposal->proceeds);
        $this->assertSame('disposed', $asset->refresh()->status);
        $this->actingAs($this->officer)->get(route('assets.show', $asset))->assertOk();
    }

    public function test_rejected_disposal_leaves_asset_in_service(): void
    {
        $director = $this->userWithRole('Director or Management');
        $asset = Asset::factory()->create();
        $this->actingAs($this->officer)->post(route('assets.disposals.store', $asset), ['action' => 'dispose', 'reason' => 'Old']);
        $approval = AssetDisposal::query()->firstOrFail()->approvals()->first();

        $this->actingAs($director)->post(route('approvals.decide', $approval), ['decision' => 'reject'])->assertSessionHasErrors('decision_comment');
        $this->actingAs($director)->post(route('approvals.decide', $approval), ['decision' => 'reject', 'decision_comment' => 'Still usable']);

        $this->assertSame('rejected', AssetDisposal::query()->first()->status);
        $this->assertSame('available', $asset->refresh()->status);
    }

    public function test_assigned_asset_must_be_returned_before_disposal_request(): void
    {
        $asset = Asset::factory()->create();
        $this->actingAs($this->officer)->post(route('assets.assign', $asset), ['user_id' => $this->officer->id, 'assigned_on' => today()->toDateString()]);

        $this->actingAs($this->officer)->post(route('assets.disposals.store', $asset), ['action' => 'retire', 'reason' => 'Old'])
            ->assertSessionHas('error');
        $this->assertSame(0, AssetDisposal::query()->count());
    }
}
