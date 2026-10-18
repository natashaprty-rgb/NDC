<?php

namespace Tests\Feature\Finance;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\Expense;
use Database\Factories\BudgetAllocationFactory;
use Database\Factories\ExpenseCategoryFactory;
use Database\Factories\FundingSourceFactory;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    public function test_finance_officer_creates_and_updates_a_draft_budget(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $department = Department::factory()->create();
        $source = FundingSourceFactory::new()->create();

        $this->actingAs($officer)->post('/budgets', [
            'title' => 'Office operations 2026', 'fiscal_year' => 2026, 'department_id' => $department->id,
            'funding_source_id' => $source->id, 'currency' => 'usd', 'period_start' => '2026-01-01', 'period_end' => '2026-12-31',
        ])->assertRedirect();

        $budget = Budget::query()->where('title', 'Office operations 2026')->firstOrFail();
        $this->assertSame('draft', $budget->status);
        $this->assertSame('USD', $budget->currency);
        $this->assertSame($officer->id, $budget->created_by);
        $this->assertMatchesRegularExpression('/^NDC-BGT-\d{4}-\d{4}$/', $budget->reference);

        $this->actingAs($officer)->put("/budgets/{$budget->id}", [
            'title' => 'Office operations FY2026', 'fiscal_year' => 2026, 'department_id' => $department->id, 'currency' => 'USD',
        ])->assertRedirect(route('budgets.show', $budget));
        $this->assertSame('Office operations FY2026', $budget->fresh()->title);
    }

    public function test_budget_validation(): void
    {
        $officer = $this->userWithRole('finance-officer');

        $this->actingAs($officer)->post('/budgets', ['title' => '', 'fiscal_year' => 1999, 'currency' => 'dollars'])
            ->assertSessionHasErrors(['title', 'fiscal_year', 'currency', 'project_id', 'department_id']);

        $this->actingAs($officer)->post('/budgets', [
            'title' => 'X', 'fiscal_year' => 2026, 'department_id' => Department::factory()->create()->id, 'currency' => 'USD',
            'period_start' => '2026-06-01', 'period_end' => '2026-05-01',
        ])->assertSessionHasErrors('period_end');

        $inactive = FundingSourceFactory::new()->create(['is_active' => false]);
        $this->actingAs($officer)->post('/budgets', [
            'title' => 'X', 'fiscal_year' => 2026, 'department_id' => Department::factory()->create()->id, 'currency' => 'USD',
            'funding_source_id' => $inactive->id,
        ])->assertSessionHasErrors('funding_source_id');
        $this->assertSame(0, Budget::query()->count());
    }

    public function test_allocations_are_added_edited_and_duplicates_rejected(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $this->actingAs($officer);
        $budget = Budget::factory()->create();
        $category = ExpenseCategoryFactory::new()->create();

        $this->post("/budgets/{$budget->id}/allocations", ['expense_category_id' => $category->id, 'amount' => '2500.50'])
            ->assertRedirect(route('budgets.show', $budget));
        $this->post("/budgets/{$budget->id}/allocations", ['expense_category_id' => $category->id, 'amount' => '10'])
            ->assertSessionHasErrors('expense_category_id');
        $this->post("/budgets/{$budget->id}/allocations", ['expense_category_id' => ExpenseCategoryFactory::new()->create()->id, 'amount' => '0'])
            ->assertSessionHasErrors('amount');
        $this->post("/budgets/{$budget->id}/allocations", ['expense_category_id' => ExpenseCategoryFactory::new()->create()->id, 'amount' => '1.234'])
            ->assertSessionHasErrors('amount');

        $allocation = BudgetAllocation::query()->where('budget_id', $budget->id)->firstOrFail();
        $this->assertSame('2500.50', $allocation->amount);

        $this->put("/budgets/{$budget->id}/allocations/{$allocation->id}", ['amount' => '3000.00', 'description' => 'Revised'])->assertRedirect();
        $this->assertSame('3000.00', $allocation->fresh()->amount);
        $this->assertSame('3000.00', $budget->allocatedTotal());
    }

    public function test_allocation_routes_are_scoped_to_their_budget(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $this->actingAs($officer);
        $budgetA = Budget::factory()->create();
        $budgetB = Budget::factory()->create();
        $allocationB = BudgetAllocationFactory::new()->create(['budget_id' => $budgetB->id]);

        $this->put("/budgets/{$budgetA->id}/allocations/{$allocationB->id}", ['amount' => '1.00'])->assertNotFound();
        $this->assertSame('1000.00', $allocationB->fresh()->amount);
    }

    public function test_submit_approve_workflow_and_preparer_cannot_approve(): void
    {
        $preparer = $this->userWithPermissions(['budgets.view', 'budgets.manage', 'budgets.approve']);
        $director = $this->userWithRole('director');

        $this->actingAs($preparer);
        $budget = Budget::factory()->create();

        // Cannot submit without allocations.
        $this->post("/budgets/{$budget->id}/submit")->assertForbidden();
        BudgetAllocationFactory::new()->create(['budget_id' => $budget->id]);
        $this->post("/budgets/{$budget->id}/submit")->assertRedirect();
        $this->assertSame('submitted', $budget->fresh()->status);
        $this->assertSame(1, $director->notifications()->count(), 'Approvers are notified');

        $this->actingAs($preparer)->post("/budgets/{$budget->id}/approve")->assertForbidden();
        $this->assertSame('submitted', $budget->fresh()->status);

        $this->actingAs($director)->post("/budgets/{$budget->id}/approve")->assertRedirect();
        $fresh = $budget->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertSame($director->id, $fresh->approved_by);

        // Allocations are locked after approval.
        $allocation = $fresh->allocations()->first();
        $this->actingAs($preparer)->put("/budgets/{$budget->id}/allocations/{$allocation->id}", ['amount' => '1.00'])->assertForbidden();
        $this->actingAs($preparer)->put("/budgets/{$budget->id}", ['title' => 'Changed', 'fiscal_year' => 2026, 'department_id' => $budget->department_id, 'currency' => 'USD'])->assertForbidden();
    }

    public function test_rejection_returns_budget_to_draft_with_reason(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $director = $this->userWithRole('director');
        $this->actingAs($officer);
        $budget = Budget::factory()->create(['status' => 'submitted']);
        BudgetAllocationFactory::new()->create(['budget_id' => $budget->id]);

        $this->actingAs($director)->post("/budgets/{$budget->id}/reject", ['review_note' => ''])->assertSessionHasErrors('review_note');
        $this->actingAs($director)->post("/budgets/{$budget->id}/reject", ['review_note' => 'Split travel costs'])->assertRedirect();

        $fresh = $budget->fresh();
        $this->assertSame('draft', $fresh->status);
        $this->assertStringContainsString('Split travel costs', $fresh->notes);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'budget', 'auditable_id' => $budget->id, 'event' => 'rejected']);
    }

    public function test_index_shows_sql_computed_figures_and_filters_by_year(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $this->actingAs($officer);
        $budget = Budget::factory()->approved()->create(['fiscal_year' => 2026, 'title' => 'Budget twenty six']);
        $other = Budget::factory()->create(['fiscal_year' => 2025, 'title' => 'Budget twenty five']);
        $allocation = BudgetAllocationFactory::new()->create(['budget_id' => $budget->id, 'amount' => '1000.00']);
        Expense::factory()->for($budget)->create(['expense_category_id' => $allocation->expense_category_id, 'amount' => '250.25', 'status' => 'approved']);
        Expense::factory()->for($budget)->create(['expense_category_id' => $allocation->expense_category_id, 'amount' => '100.00', 'status' => 'recorded']);

        $this->get('/budgets?fiscal_year=2026')
            ->assertOk()
            ->assertSee('Budget twenty six')->assertDontSee('Budget twenty five')
            ->assertSee('USD 1,000.00')->assertSee('USD 250.25')->assertSee('USD 749.75');
    }

    public function test_auditor_can_view_but_not_change_budgets(): void
    {
        $auditor = $this->userWithRole('auditor');
        $budget = Budget::factory()->create();

        $this->actingAs($auditor)->get('/budgets')->assertOk();
        $this->actingAs($auditor)->get("/budgets/{$budget->id}")->assertOk();
        $this->actingAs($auditor)->get('/budgets/create')->assertForbidden();
        $this->actingAs($auditor)->post('/budgets', ['title' => 'X', 'fiscal_year' => 2026, 'department_id' => $budget->department_id, 'currency' => 'USD'])->assertForbidden();
        $this->actingAs($auditor)->post("/budgets/{$budget->id}/allocations", ['expense_category_id' => 1, 'amount' => '1'])->assertForbidden();
        $this->actingAs($auditor)->delete("/budgets/{$budget->id}")->assertForbidden();
    }

    public function test_users_without_budget_permission_get_403(): void
    {
        $staff = $this->userWithRole('staff');
        $this->actingAs($staff)->get('/budgets')->assertForbidden();
    }

    public function test_draft_budget_without_expenses_can_be_deleted(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $this->actingAs($officer);
        $budget = Budget::factory()->create();

        $this->delete("/budgets/{$budget->id}")->assertRedirect(route('budgets.index'));
        $this->assertSoftDeleted($budget);
    }
}
