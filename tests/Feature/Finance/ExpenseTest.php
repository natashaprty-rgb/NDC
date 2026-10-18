<?php

namespace Tests\Feature\Finance;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Expense;
use App\Models\User;
use Database\Factories\BudgetAllocationFactory;
use Database\Factories\ExpenseCategoryFactory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    private function approvedBudget(string $allocated = '1000.00', array $budgetAttributes = []): array
    {
        $budget = Budget::factory()->approved()->create($budgetAttributes);
        $allocation = BudgetAllocationFactory::new()->create(['budget_id' => $budget->id, 'amount' => $allocated]);

        return [$budget, $allocation];
    }

    private function payload(Budget $budget, BudgetAllocation $allocation, array $overrides = []): array
    {
        return array_merge([
            'budget_id' => $budget->id,
            'expense_category_id' => $allocation->expense_category_id,
            'description' => 'Printer toner',
            'payee' => 'Local vendor',
            'amount' => '125.40',
            'expense_date' => today()->toDateString(),
        ], $overrides);
    }

    public function test_officer_records_expense_with_receipt_and_approvers_are_notified(): void
    {
        Storage::fake('local');
        $officer = $this->userWithRole('finance-officer');
        $director = $this->userWithRole('director');
        [$budget, $allocation] = $this->approvedBudget();

        $receipt = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->actingAs($officer)->post('/expenses', $this->payload($budget, $allocation) + ['receipt' => $receipt])->assertRedirect();

        $expense = Expense::query()->firstOrFail();
        $this->assertSame('recorded', $expense->status);
        $this->assertSame('125.40', $expense->amount);
        $this->assertSame($officer->id, $expense->recorded_by);
        $this->assertSame('USD', $expense->currency);
        $this->assertMatchesRegularExpression('/^NDC-EXP-\d{4}-\d{4}$/', $expense->reference);
        $this->assertSame(1, $expense->attachments()->where('category', 'receipt')->count());
        Storage::disk('local')->assertExists($expense->attachments()->first()->path);
        $this->assertSame(1, $director->notifications()->count());
    }

    public function test_cannot_record_against_unapproved_budget_or_unallocated_category(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $this->actingAs($officer);
        $draft = Budget::factory()->create(['status' => 'draft']);
        $draftAllocation = BudgetAllocationFactory::new()->create(['budget_id' => $draft->id]);
        $this->post('/expenses', $this->payload($draft, $draftAllocation))->assertSessionHasErrors('budget_id');

        $closed = Budget::factory()->create(['status' => 'closed']);
        $closedAllocation = BudgetAllocationFactory::new()->create(['budget_id' => $closed->id]);
        $this->post('/expenses', $this->payload($closed, $closedAllocation))->assertSessionHasErrors('budget_id');

        [$budget, $allocation] = $this->approvedBudget();
        $otherCategory = ExpenseCategoryFactory::new()->create();
        $this->post('/expenses', $this->payload($budget, $allocation, ['expense_category_id' => $otherCategory->id]))
            ->assertSessionHasErrors('expense_category_id');

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_expense_validation_rules(): void
    {
        $officer = $this->userWithRole('finance-officer');
        [$budget, $allocation] = $this->approvedBudget('1000.00', ['period_start' => '2026-01-01', 'period_end' => '2026-06-30']);
        $this->actingAs($officer);

        $this->post('/expenses', $this->payload($budget, $allocation, ['amount' => '-5', 'description' => '']))->assertSessionHasErrors(['amount', 'description']);
        $this->post('/expenses', $this->payload($budget, $allocation, ['amount' => '10.001']))->assertSessionHasErrors('amount');
        $this->post('/expenses', $this->payload($budget, $allocation, ['expense_date' => today()->addDay()->toDateString()]))->assertSessionHasErrors('expense_date');
        $this->post('/expenses', $this->payload($budget, $allocation, ['expense_date' => '2025-12-31']))->assertSessionHasErrors('expense_date');
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_recorder_cannot_approve_own_expense(): void
    {
        $user = $this->userWithPermissions(['expenses.view', 'expenses.record', 'expenses.approve']);
        [$budget, $allocation] = $this->approvedBudget();

        $this->actingAs($user)->post('/expenses', $this->payload($budget, $allocation))->assertRedirect();
        $expense = Expense::query()->firstOrFail();

        $this->actingAs($user)->post("/expenses/{$expense->id}/approve")->assertForbidden();
        $this->assertSame('recorded', $expense->fresh()->status);
    }

    public function test_approval_cannot_exceed_allocation_to_the_cent(): void
    {
        $director = $this->userWithRole('director');
        [$budget, $allocation] = $this->approvedBudget('1000.00');
        $category = $allocation->expense_category_id;
        Expense::factory()->for($budget)->create(['expense_category_id' => $category, 'amount' => '999.99', 'status' => 'approved']);
        // Rejected and merely recorded expenses do not consume the allocation.
        Expense::factory()->for($budget)->create(['expense_category_id' => $category, 'amount' => '500.00', 'status' => 'rejected']);

        $tooMuch = Expense::factory()->for($budget)->create(['expense_category_id' => $category, 'amount' => '0.02']);
        $exact = Expense::factory()->for($budget)->create(['expense_category_id' => $category, 'amount' => '0.01']);

        $this->actingAs($director)->post("/expenses/{$tooMuch->id}/approve")->assertSessionHasErrors('amount');
        $this->assertSame('recorded', $tooMuch->fresh()->status);

        $this->actingAs($director)->post("/expenses/{$exact->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame('approved', $exact->fresh()->status);
        $this->assertSame($director->id, $exact->fresh()->approved_by);
        $this->assertSame('0.00', $budget->summary()['remaining']);

        // Now even one cent more is refused.
        $this->actingAs($director)->post("/expenses/{$tooMuch->id}/approve")->assertSessionHasErrors('amount');
    }

    public function test_reject_requires_reason_and_notifies_recorder(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $director = $this->userWithRole('director');
        [$budget, $allocation] = $this->approvedBudget();
        $expense = Expense::factory()->for($budget)->create(['expense_category_id' => $allocation->expense_category_id, 'recorded_by' => $officer->id]);

        $this->actingAs($director)->post("/expenses/{$expense->id}/reject", ['review_note' => ''])->assertSessionHasErrors('review_note');
        $this->actingAs($director)->post("/expenses/{$expense->id}/reject", ['review_note' => 'No receipt'])->assertRedirect();

        $fresh = $expense->fresh();
        $this->assertSame('rejected', $fresh->status);
        $this->assertSame($director->id, $fresh->rejected_by);
        $this->assertSame('No receipt', $fresh->review_note);
        $this->assertSame(1, $officer->notifications()->count());
    }

    public function test_settlement_is_recorded_by_someone_other_than_the_approver(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $approverWhoSettles = $this->userWithPermissions(['expenses.view', 'expenses.approve', 'expenses.settle']);
        [$budget, $allocation] = $this->approvedBudget();
        $expense = Expense::factory()->for($budget)->create(['expense_category_id' => $allocation->expense_category_id, 'recorded_by' => $officer->id]);

        $this->actingAs($approverWhoSettles)->post("/expenses/{$expense->id}/approve")->assertRedirect();
        $this->actingAs($approverWhoSettles)->post("/expenses/{$expense->id}/paid", ['paid_on' => today()->toDateString()])->assertForbidden();

        $this->actingAs($officer)->post("/expenses/{$expense->id}/paid", ['paid_on' => today()->addDay()->toDateString()])->assertSessionHasErrors('paid_on');
        $this->actingAs($officer)->post("/expenses/{$expense->id}/paid", ['paid_on' => today()->toDateString(), 'payment_reference' => 'TRX-77'])->assertRedirect();
        $this->assertSame('paid', $expense->fresh()->status);
        $this->assertSame('TRX-77', $expense->fresh()->payment_reference);

        $this->actingAs($officer)->post("/expenses/{$expense->id}/reconcile")->assertRedirect();
        $this->assertSame('reconciled', $expense->fresh()->status);
        $this->assertNotNull($expense->fresh()->reconciled_at);
    }

    public function test_budget_summary_distinguishes_recorded_approved_paid_and_remaining(): void
    {
        $director = $this->userWithRole('director');
        [$budget, $allocation] = $this->approvedBudget('1000.00');
        $second = BudgetAllocationFactory::new()->create(['budget_id' => $budget->id, 'amount' => '500.00']);
        $cat = $allocation->expense_category_id;
        foreach (['recorded' => '10.10', 'approved' => '20.20', 'paid' => '30.30', 'reconciled' => '40.40', 'rejected' => '50.50'] as $status => $amount) {
            Expense::factory()->for($budget)->create(['expense_category_id' => $cat, 'amount' => $amount, 'status' => $status]);
        }

        $summary = $budget->summary();
        $this->assertSame('1500.00', $summary['allocated']);
        $this->assertSame('101.00', $summary['recorded']);
        $this->assertSame('90.90', $summary['approved']);
        $this->assertSame('70.70', $summary['paid']);
        $this->assertSame('40.40', $summary['reconciled']);
        $this->assertSame('1409.10', $summary['remaining']);
        $this->assertSame('1399.00', $summary['remaining_after_recorded']);
        $this->assertSame('909.10', $budget->summary($cat)['remaining']);
        $this->assertSame('500.00', $budget->summary($second->expense_category_id)['remaining']);

        $this->actingAs($director)->get("/budgets/{$budget->id}")->assertOk()
            ->assertSee('USD 1,409.10')->assertSee('USD 101.00')->assertSee('USD 70.70');
    }

    public function test_index_filters_and_totals_row(): void
    {
        $auditor = $this->userWithRole('auditor');
        [$budget, $allocation] = $this->approvedBudget();
        [$otherBudget, $otherAllocation] = $this->approvedBudget();
        $cat = $allocation->expense_category_id;
        Expense::factory()->for($budget)->create(['expense_category_id' => $cat, 'amount' => '0.10', 'status' => 'approved', 'description' => 'Alpha item']);
        Expense::factory()->for($budget)->create(['expense_category_id' => $cat, 'amount' => '0.20', 'status' => 'approved', 'description' => 'Beta item']);
        Expense::factory()->for($budget)->create(['expense_category_id' => $cat, 'amount' => '1000.00', 'status' => 'recorded', 'description' => 'Gamma item']);
        Expense::factory()->for($otherBudget)->create(['expense_category_id' => $otherAllocation->expense_category_id, 'amount' => '7.00', 'description' => 'Other budget item']);

        $this->actingAs($auditor)->get("/expenses?budget_id={$budget->id}&status=approved")
            ->assertOk()->assertSee('Alpha item')->assertSee('Beta item')->assertDontSee('Gamma item')->assertDontSee('Other budget item')
            ->assertSee('USD 0.30');

        $this->actingAs($auditor)->get("/expenses?budget_id={$budget->id}")->assertSee('USD 1,000.30');
        $this->actingAs($auditor)->get('/expenses?q=Gamma')->assertSee('Gamma item')->assertDontSee('Alpha item');
    }

    public function test_only_recorder_can_edit_or_delete_while_recorded(): void
    {
        $officer = $this->userWithRole('finance-officer');
        $colleague = $this->userWithRole('finance-officer');
        [$budget, $allocation] = $this->approvedBudget();
        $this->actingAs($officer)->post('/expenses', $this->payload($budget, $allocation))->assertRedirect();
        $expense = Expense::query()->firstOrFail();

        $this->actingAs($colleague)->put("/expenses/{$expense->id}", $this->payload($budget, $allocation, ['amount' => '1.00']))->assertForbidden();
        $this->actingAs($officer)->put("/expenses/{$expense->id}", $this->payload($budget, $allocation, ['amount' => '99.99']))->assertRedirect();
        $this->assertSame('99.99', $expense->fresh()->amount);

        $expense->forceFill(['status' => 'approved'])->save();
        $this->actingAs($officer)->delete("/expenses/{$expense->id}")->assertForbidden();
        $this->assertNotSoftDeleted($expense);
    }

    public function test_auditor_can_view_but_not_post(): void
    {
        $auditor = $this->userWithRole('auditor');
        [$budget, $allocation] = $this->approvedBudget();
        $expense = Expense::factory()->for($budget)->create(['expense_category_id' => $allocation->expense_category_id]);

        $this->actingAs($auditor)->get('/expenses')->assertOk();
        $this->actingAs($auditor)->get("/expenses/{$expense->id}")->assertOk()->assertSee($expense->reference);
        $this->actingAs($auditor)->post('/expenses', $this->payload($budget, $allocation))->assertForbidden();
        $this->actingAs($auditor)->post("/expenses/{$expense->id}/approve")->assertForbidden();
        $this->actingAs($auditor)->post("/expenses/{$expense->id}/paid", ['paid_on' => today()->toDateString()])->assertForbidden();
        $this->assertSame(1, Expense::query()->count());
    }

    public function test_form_pages_render_for_authorised_users_in_both_languages(): void
    {
        $officer = $this->userWithRole('finance-officer');
        [$budget, $allocation] = $this->approvedBudget();
        $this->actingAs($officer)->post('/expenses', $this->payload($budget, $allocation))->assertRedirect();
        $expense = Expense::query()->firstOrFail();
        $draft = Budget::factory()->create();

        foreach (['en', 'so'] as $locale) {
            $officer->update(['locale' => $locale]);
            $this->actingAs($officer->fresh())->get('/budgets/create')->assertOk();
            $this->actingAs($officer->fresh())->get("/budgets/{$draft->id}/edit")->assertOk();
            $this->actingAs($officer->fresh())->get("/expenses/create?budget_id={$budget->id}")->assertOk()->assertSee($budget->reference);
            $this->actingAs($officer->fresh())->get("/expenses/{$expense->id}/edit")->assertOk();
        }
        $this->actingAs($officer->fresh())->get('/expenses')->assertSee('Kharashaadka');
    }
}
