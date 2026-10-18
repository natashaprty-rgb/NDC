<?php

namespace Tests\Feature\Procurement;

use App\Models\PurchaseRequest;
use Tests\TestCase;

class PurchaseRequestTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Office chairs',
            'currency' => 'USD',
            'items' => [
                ['description' => 'Chair', 'quantity' => '3', 'unit' => 'pcs', 'estimated_unit_cost' => '33.33'],
                ['description' => 'Cable', 'quantity' => '1.5', 'unit' => 'm', 'estimated_unit_cost' => '2.47'],
            ],
        ], $overrides);
    }

    public function test_requester_creates_draft_with_exact_server_side_totals(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->post('/purchase-requests', $this->payload())->assertRedirect();

        $pr = PurchaseRequest::query()->with('items')->firstOrFail();
        $this->assertSame('draft', $pr->status);
        $this->assertSame($staff->id, $pr->requested_by);
        $this->assertStringStartsWith('NDC-PR-', $pr->reference);
        $this->assertSame(['99.99', '3.71'], $pr->items->sortBy('id')->pluck('line_total')->values()->all());
        $this->assertSame('103.70', $pr->estimated_total);
    }

    public function test_validation_rejects_bad_items(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)->post('/purchase-requests', $this->payload(['items' => []]))->assertSessionHasErrors('items');
        $this->actingAs($staff)->post('/purchase-requests', $this->payload(['items' => [['description' => 'X', 'quantity' => '0', 'estimated_unit_cost' => '1']]]))
            ->assertSessionHasErrors('items.0.quantity');
        $this->actingAs($staff)->post('/purchase-requests', $this->payload(['items' => [['description' => 'X', 'quantity' => '1', 'estimated_unit_cost' => '-5']]]))
            ->assertSessionHasErrors('items.0.estimated_unit_cost');
        $this->actingAs($staff)->post('/purchase-requests', $this->payload(['items' => [['description' => 'X', 'quantity' => '1', 'estimated_unit_cost' => '1.234']]]))
            ->assertSessionHasErrors('items.0.estimated_unit_cost');
        $this->assertSame(0, PurchaseRequest::query()->count());
    }

    public function test_editing_replaces_items_atomically(): void
    {
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->post('/purchase-requests', $this->payload());
        $pr = PurchaseRequest::query()->firstOrFail();

        $this->actingAs($staff)->put("/purchase-requests/{$pr->id}", $this->payload([
            'items' => [['description' => 'Desk', 'quantity' => '2', 'estimated_unit_cost' => '150.00']],
        ]))->assertRedirect();

        $this->assertSame(1, $pr->items()->count());
        $this->assertSame('300.00', $pr->fresh()->estimated_total);
    }

    public function test_staff_only_see_their_own_requests(): void
    {
        $alice = $this->userWithRole('staff');
        $bob = $this->userWithRole('staff');
        $mine = PurchaseRequest::factory()->withItem()->create(['requested_by' => $alice->id, 'title' => 'Alice laptops']);
        $theirs = PurchaseRequest::factory()->withItem()->create(['requested_by' => $bob->id, 'title' => 'Bob projector']);

        $this->actingAs($alice)->get('/purchase-requests')->assertOk()->assertSee('Alice laptops')->assertDontSee('Bob projector');
        $this->actingAs($alice)->get("/purchase-requests/{$theirs->id}")->assertForbidden();
        $this->actingAs($alice)->get("/purchase-requests/{$mine->id}")->assertOk();

        // Oversight roles see everything.
        $this->actingAs($this->userWithRole('director'))->get('/purchase-requests')->assertSee('Alice laptops')->assertSee('Bob projector');
    }

    public function test_cannot_edit_after_submission_and_approvers_are_notified(): void
    {
        $staff = $this->userWithRole('staff');
        $director = $this->userWithRole('director');
        $pr = PurchaseRequest::factory()->withItem()->create(['requested_by' => $staff->id]);

        $this->actingAs($staff)->post("/purchase-requests/{$pr->id}/submit")->assertRedirect();
        $this->assertSame('submitted', $pr->fresh()->status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $director->id, 'notifiable_type' => 'user']);

        $this->actingAs($staff)->put("/purchase-requests/{$pr->id}", $this->payload())->assertForbidden();
        $this->actingAs($staff)->post("/purchase-requests/{$pr->id}/submit")->assertForbidden();
    }

    public function test_requester_cannot_approve_own_request_even_with_approval_permission(): void
    {
        $head = $this->userWithRole('department-head'); // holds procurement.request and procurement.approve
        $pr = PurchaseRequest::factory()->withItem()->create(['requested_by' => $head->id, 'status' => 'submitted']);

        $this->actingAs($head)->post("/purchase-requests/{$pr->id}/approve")->assertForbidden();
        $this->assertSame('submitted', $pr->fresh()->status);
    }

    public function test_approver_approves_and_requester_is_notified(): void
    {
        $staff = $this->userWithRole('staff');
        $director = $this->userWithRole('director');
        $pr = PurchaseRequest::factory()->withItem()->create(['requested_by' => $staff->id, 'status' => 'submitted']);

        $this->actingAs($director)->post("/purchase-requests/{$pr->id}/approve", ['decision_note' => 'OK'])->assertRedirect();

        $pr->refresh();
        $this->assertSame('approved', $pr->status);
        $this->assertSame($director->id, $pr->decided_by);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $staff->id]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'purchase_request', 'auditable_id' => $pr->id, 'event' => 'approved']);

        // Already decided: cannot be decided again.
        $this->actingAs($director)->post("/purchase-requests/{$pr->id}/reject", ['decision_note' => 'No'])->assertForbidden();
    }

    public function test_rejection_requires_a_note(): void
    {
        $staff = $this->userWithRole('staff');
        $director = $this->userWithRole('director');
        $pr = PurchaseRequest::factory()->withItem()->create(['requested_by' => $staff->id, 'status' => 'submitted']);

        $this->actingAs($director)->post("/purchase-requests/{$pr->id}/reject", ['decision_note' => ''])->assertSessionHasErrors('decision_note');
        $this->assertSame('submitted', $pr->fresh()->status);

        $this->actingAs($director)->post("/purchase-requests/{$pr->id}/reject", ['decision_note' => 'Not in budget'])->assertRedirect();
        $this->assertSame('rejected', $pr->fresh()->status);
        $this->assertSame('Not in budget', $pr->fresh()->decision_note);
    }

    public function test_cancel_requires_reason_and_unauthorised_user_cannot_cancel(): void
    {
        $staff = $this->userWithRole('staff');
        $other = $this->userWithRole('staff');
        $pr = PurchaseRequest::factory()->withItem()->create(['requested_by' => $staff->id, 'status' => 'submitted']);

        $this->actingAs($other)->post("/purchase-requests/{$pr->id}/cancel", ['reason' => 'x'])->assertForbidden();
        $this->actingAs($staff)->post("/purchase-requests/{$pr->id}/cancel", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($staff)->post("/purchase-requests/{$pr->id}/cancel", ['reason' => 'No longer needed'])->assertRedirect();
        $this->assertSame('cancelled', $pr->fresh()->status);
    }
}
