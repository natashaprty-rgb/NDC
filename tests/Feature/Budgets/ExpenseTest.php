<?php

namespace Tests\Feature\Budgets;

use App\Models\Approval;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use RefreshDatabase;

    private Category $equipment;

    private Category $training;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        $this->equipment = Category::query()->create(['type' => 'expense', 'name' => 'Equipment', 'is_active' => true]);
        $this->training = Category::query()->create(['type' => 'expense', 'name' => 'Training', 'is_active' => true]);
    }

    private function finance(): User
    {
        return $this->userWithRole('Finance or Procurement Officer');
    }

    private function director(): User
    {
        return $this->userWithRole('Director or Management');
    }

    private function budget(array $attributes = []): Budget
    {
        return Budget::factory()->approved()->create(array_merge([
            'amount' => 1000,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Budget $budget, array $overrides = []): array
    {
        return array_merge([
            'budget_id' => $budget->id,
            'category_id' => $this->equipment->id,
            'description' => 'Network switches',
            'payee' => 'Horn Tech Ltd',
            'reference' => 'INV-001',
            'amount' => '400.00',
            'expense_date' => '2026-03-15',
        ], $overrides);
    }

    private function record(User $user, Budget $budget, array $overrides = []): Expense
    {
        $this->actingAs($user)->post(route('expenses.store'), $this->payload($budget, $overrides))->assertSessionHasNoErrors();

        return Expense::query()->latest('id')->firstOrFail();
    }

    private function decide(User $user, Expense $expense, bool $approve, ?string $comment = null): void
    {
        $approval = $expense->pendingApproval()->firstOrFail();
        $this->actingAs($user)->post(route('approvals.decide', $approval), array_filter([
            'decision' => $approve ? 'approve' : 'reject',
            'decision_comment' => $comment,
        ]))->assertRedirect();
    }

    public function test_expense_is_recorded_as_pending_with_an_approval_request(): void
    {
        $officer = $this->finance();
        $budget = $this->budget();

        $response = $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget));
        $expense = Expense::query()->firstOrFail();

        $response->assertRedirect(route('expenses.show', $expense))->assertSessionMissing('warning');
        $this->assertSame('pending', $expense->status);
        $this->assertSame($budget->id, $expense->budget_id);
        $this->assertSame($officer->id, $expense->recorded_by);
        $this->assertSame('expenses.approve', $expense->pendingApproval->permission);
        $this->assertSame(0, Budget::cents($budget->approvedSpend()));
    }

    public function test_expenses_can_only_be_recorded_against_approved_open_budgets(): void
    {
        $officer = $this->finance();

        foreach (['draft', 'submitted', 'rejected', 'closed'] as $status) {
            $budget = $this->budget(['status' => $status]);
            $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget))
                ->assertSessionHasErrors('budget_id');
        }
        $this->assertSame(0, Expense::query()->count());

        $closed = Budget::query()->where('status', 'closed')->firstOrFail();
        $this->actingAs($officer)->get(route('expenses.create', ['budget' => $closed->id]))->assertForbidden();
        $this->actingAs($officer)->get(route('budgets.show', $closed))->assertOk()->assertDontSee(route('expenses.create', ['budget' => $closed->id]));
    }

    public function test_expense_validation(): void
    {
        $officer = $this->finance();
        $budget = $this->budget();

        $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget, ['amount' => '0']))->assertSessionHasErrors('amount');
        $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget, ['amount' => '-5']))->assertSessionHasErrors('amount');
        $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget, ['expense_date' => '2025-12-31']))->assertSessionHasErrors('expense_date');
        $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget, ['expense_date' => '2027-01-01']))->assertSessionHasErrors('expense_date');
        $source = Category::query()->create(['type' => 'funding_source', 'name' => 'Donor', 'is_active' => true]);
        $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget, ['category_id' => $source->id]))->assertSessionHasErrors('category_id');
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_approval_by_another_user_updates_remaining_and_utilisation(): void
    {
        $officer = $this->finance();
        $director = $this->director();
        $budget = $this->budget();
        $expense = $this->record($officer, $budget, ['amount' => '850']);

        // The recorder cannot approve their own expense.
        $approval = $expense->pendingApproval()->firstOrFail();
        $this->actingAs($officer)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('pending', $expense->fresh()->status);

        // Even a user holding expenses.approve cannot approve an expense they recorded.
        $selfApprover = $this->userWithPermissions(['budgets.view', 'expenses.create', 'expenses.approve']);
        $own = $this->record($selfApprover, $budget, ['amount' => '10']);
        $this->actingAs($selfApprover)->post(route('approvals.decide', $own->pendingApproval), ['decision' => 'approve'])->assertForbidden();

        $this->decide($director, $expense, true);

        $budget->refresh();
        $this->assertSame('approved', $expense->fresh()->status);
        $this->assertEqualsWithDelta(150.0, $budget->remaining(), 0.001);
        $this->assertSame(85, $budget->utilisation());
        $summary = $budget->spendSummary();
        $this->assertEqualsWithDelta(850.0, $summary['approved'], 0.001);
        $this->assertEqualsWithDelta(10.0, $summary['pending'], 0.001);

        $this->actingAs($director)->get(route('budgets.show', $budget))->assertOk()
            ->assertSee('USD 150.00')
            ->assertSee('progress__bar--warn', false);

        // Approved expenses are locked, for Super Administrators too.
        $this->actingAs($officer)->get(route('expenses.edit', $expense))->assertForbidden();
        $this->actingAs($officer)->delete(route('expenses.destroy', $expense))->assertForbidden();
        $this->actingAs($this->superAdmin())->delete(route('expenses.destroy', $expense))->assertForbidden();
        $this->assertNotSoftDeleted($expense);
    }

    public function test_over_budget_warning_is_shown_but_does_not_block(): void
    {
        $officer = $this->finance();
        $budget = $this->budget(['amount' => 1000]);
        $this->record($officer, $budget, ['amount' => '700']);

        $response = $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget, ['amount' => '400']));
        $response->assertSessionHas('warning');
        $this->assertSame(2, Expense::query()->count());

        $this->actingAs($officer)->get(route('budgets.show', $budget))->assertOk()
            ->assertSee(__('Approved and pending expenses (:committed) exceed the budget amount of :amount. Review pending expenses before approving them.', ['committed' => 'USD 1,100.00', 'amount' => 'USD 1,000.00']));
    }

    public function test_rejected_expense_is_not_counted_and_closed_budget_refuses_expenses(): void
    {
        $officer = $this->finance();
        $director = $this->director();
        $budget = $this->budget();

        $good = $this->record($officer, $budget, ['amount' => '300']);
        $bad = $this->record($officer, $budget, ['amount' => '500']);

        $this->actingAs($director)->post(route('approvals.decide', $bad->pendingApproval), ['decision' => 'reject'])
            ->assertSessionHasErrors('decision_comment');
        $this->decide($director, $bad, false, 'Duplicate invoice.');
        $this->decide($director, $good, true);

        $budget->refresh();
        $this->assertSame('rejected', $bad->fresh()->status);
        $this->assertEqualsWithDelta(300.0, $budget->approvedSpend(), 0.001);
        $this->assertEqualsWithDelta(0.0, $budget->pendingSpend(), 0.001);
        $this->assertEqualsWithDelta(700.0, $budget->remaining(), 0.001);

        $this->actingAs($director)->post(route('budgets.close', $budget))->assertSessionHas('success');
        $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget))->assertSessionHasErrors('budget_id');
        $this->assertSame(2, Expense::query()->count());
    }

    public function test_planned_vs_actual_by_category(): void
    {
        $budget = $this->budget(['amount' => 10000]);
        $budget->allocations()->create(['category_id' => $this->equipment->id, 'planned_amount' => 6000]);
        $budget->allocations()->create(['category_id' => $this->equipment->id, 'planned_amount' => 1000.50]);
        $budget->allocations()->create(['category_id' => $this->training->id, 'planned_amount' => 2000]);

        Expense::factory()->approved()->create(['budget_id' => $budget->id, 'category_id' => $this->equipment->id, 'amount' => 2500.25]);
        Expense::factory()->approved()->create(['budget_id' => $budget->id, 'category_id' => $this->training->id, 'amount' => 2500]);
        Expense::factory()->create(['budget_id' => $budget->id, 'category_id' => $this->training->id, 'amount' => 100]);
        Expense::factory()->rejected()->create(['budget_id' => $budget->id, 'category_id' => $this->training->id, 'amount' => 9999]);

        $rows = $budget->categoryBreakdown()->keyBy('category');

        $this->assertEqualsWithDelta(7000.50, $rows['Equipment']['planned'], 0.001);
        $this->assertEqualsWithDelta(2500.25, $rows['Equipment']['approved'], 0.001);
        $this->assertEqualsWithDelta(4500.25, $rows['Equipment']['variance'], 0.001);
        $this->assertSame(36, $rows['Equipment']['utilisation']);

        $this->assertEqualsWithDelta(2000.0, $rows['Training']['planned'], 0.001);
        $this->assertEqualsWithDelta(2500.0, $rows['Training']['approved'], 0.001);
        $this->assertEqualsWithDelta(100.0, $rows['Training']['pending'], 0.001);
        $this->assertEqualsWithDelta(-500.0, $rows['Training']['variance'], 0.001);
        $this->assertSame(125, $rows['Training']['utilisation']);

        $this->actingAs($this->director())->get(route('budgets.show', $budget))->assertOk()
            ->assertSeeInOrder(['Equipment', 'USD 7,000.50', 'USD 2,500.25', 'USD 0.00', 'USD 4,500.25'])
            ->assertSeeInOrder(['Training', 'USD 2,000.00', 'USD 2,500.00', 'USD 100.00', 'USD -500.00']);
    }

    public function test_receipts_can_be_uploaded_with_and_after_recording(): void
    {
        $officer = $this->finance();
        $budget = $this->budget();

        $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget, [
            'receipts' => [UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf')],
        ]))->assertSessionHasNoErrors();

        $expense = Expense::query()->firstOrFail();
        $attachment = $expense->attachments()->firstOrFail();
        $this->assertSame('receipts', $attachment->collection);
        Storage::disk('local')->assertExists($attachment->path);

        // Upload through the shared attachment controller while still pending.
        $this->actingAs($officer)->post(route('attachments.store'), [
            'attachable_type' => 'expense',
            'attachable_id' => $expense->id,
            'collection' => 'receipts',
            'file' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(2, $expense->attachments()->count());

        $this->actingAs($officer)->get(route('expenses.show', $expense))->assertOk()->assertSee('invoice.pdf')->assertSee('receipt.pdf');
        $this->actingAs($this->userWithRole('Read-Only Auditor'))->get(route('attachments.download', $attachment))->assertOk();

        // Disallowed file types are rejected.
        $this->actingAs($officer)->post(route('expenses.store'), $this->payload($budget, [
            'receipts' => [UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream')],
        ]))->assertSessionHasErrors('receipts.0');
    }

    public function test_pending_expense_can_be_edited_and_withdrawn_by_recorder_only(): void
    {
        $officer = $this->finance();
        $otherRecorder = $this->userWithRole('Project Manager'); // expenses.create, no budgets.update
        $budget = $this->budget();
        $expense = $this->record($officer, $budget);

        $this->actingAs($otherRecorder)->get(route('expenses.edit', $expense))->assertForbidden();
        $this->actingAs($otherRecorder)->delete(route('expenses.destroy', $expense))->assertForbidden();

        $this->actingAs($officer)->put(route('expenses.update', $expense), $this->payload($budget, ['amount' => '450', 'description' => 'Switches and cables']))
            ->assertRedirect(route('expenses.show', $expense));
        $this->assertEquals('450.00', $expense->fresh()->amount);
        $this->assertSame($budget->id, $expense->fresh()->budget_id);

        $approval = $expense->pendingApproval()->firstOrFail();
        $this->actingAs($officer)->delete(route('expenses.destroy', $expense))->assertRedirect(route('budgets.show', $budget));
        $this->assertSoftDeleted($expense);
        $this->assertSame('cancelled', $approval->fresh()->status);
        $this->assertSame(0, Budget::cents($budget->pendingSpend()));
    }

    public function test_expense_register_filters_and_total(): void
    {
        $director = $this->director();
        $budgetA = $this->budget(['name' => 'Budget A']);
        $budgetB = $this->budget(['name' => 'Budget B']);

        Expense::factory()->approved()->create(['budget_id' => $budgetA->id, 'description' => 'Alpha laptops', 'amount' => 100.10, 'category_id' => $this->equipment->id, 'expense_date' => '2026-02-01']);
        Expense::factory()->create(['budget_id' => $budgetA->id, 'description' => 'Beta course', 'amount' => 200.20, 'category_id' => $this->training->id, 'expense_date' => '2026-03-01']);
        Expense::factory()->rejected()->create(['budget_id' => $budgetB->id, 'description' => 'Gamma chairs', 'amount' => 300.30, 'category_id' => $this->equipment->id, 'expense_date' => '2026-04-01']);

        $this->actingAs($director)->get(route('expenses.index'))->assertOk()
            ->assertSee('Alpha laptops')->assertSee('Gamma chairs')->assertSee('USD 600.60');

        $this->actingAs($director)->get(route('expenses.index', ['budget' => $budgetA->id]))
            ->assertSee('Beta course')->assertDontSee('Gamma chairs')->assertSee('USD 300.30');
        $this->actingAs($director)->get(route('expenses.index', ['category' => $this->equipment->id]))
            ->assertSee('Alpha laptops')->assertDontSee('Beta course');
        $this->actingAs($director)->get(route('expenses.index', ['status' => 'approved']))
            ->assertSee('Alpha laptops')->assertDontSee('Gamma chairs');
        $this->actingAs($director)->get(route('expenses.index', ['from' => '2026-02-15', 'to' => '2026-03-15']))
            ->assertSee('Beta course')->assertDontSee('Alpha laptops')->assertDontSee('Gamma chairs');
        $this->actingAs($director)->get(route('expenses.index', ['q' => 'Gamma']))
            ->assertSee('Gamma chairs')->assertDontSee('Alpha laptops');

        $this->actingAs($director)->get(route('expenses.report', ['budget' => $budgetA->id]))->assertOk()
            ->assertSee('Alpha laptops')->assertDontSee('Gamma chairs')->assertSee('data-print', false);
        $this->actingAs($director)->get(route('budgets.report'))->assertOk()
            ->assertSee('Budget A')->assertSee('Budget B');
    }

    public function test_project_filter_on_expense_register(): void
    {
        $director = $this->director();
        $project = Project::query()->create(['code' => 'PRJ-9', 'title' => 'Fibre backbone']);
        $withProject = $this->budget(['project_id' => $project->id]);
        $without = $this->budget();
        Expense::factory()->create(['budget_id' => $withProject->id, 'description' => 'Fibre cable']);
        Expense::factory()->create(['budget_id' => $without->id, 'description' => 'Office paper']);

        $this->actingAs($director)->get(route('expenses.index', ['project' => $project->id]))
            ->assertSee('Fibre cable')->assertDontSee('Office paper');
    }

    public function test_approval_inbox_shows_expense_to_approver(): void
    {
        $officer = $this->finance();
        $director = $this->director();
        $expense = $this->record($officer, $this->budget());

        $this->actingAs($director)->get(route('approvals.index'))->assertOk()->assertSee(route('expenses.show', $expense));
        $this->actingAs($director)->get(route('expenses.show', $expense))->assertOk()->assertSee(__('Approve'));
        $this->assertSame(1, Approval::query()->where('status', 'pending')->count());
    }
}
