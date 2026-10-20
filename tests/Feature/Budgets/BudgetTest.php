<?php

namespace Tests\Feature\Budgets;

use App\Models\Approval;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Project;
use App\Models\User;
use App\Services\ApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Category $equipment;

    private Category $training;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Notification::fake();

        $this->department = Department::query()->create(['name' => 'ICT', 'code' => 'ICT', 'is_active' => true]);
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

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'ICT Operations 2026',
            'fiscal_year' => '2026',
            'department_id' => $this->department->id,
            'amount' => '10000.00',
            'currency' => 'usd',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'notes' => 'Approved by the board.',
            'allocations' => [
                ['category_id' => $this->equipment->id, 'planned_amount' => '6000', 'description' => 'Laptops'],
                ['category_id' => $this->training->id, 'planned_amount' => '3000.50', 'description' => ''],
            ],
        ], $overrides);
    }

    public function test_finance_officer_creates_budget_with_generated_code_and_allocations(): void
    {
        $user = $this->finance();

        $response = $this->actingAs($user)->post(route('budgets.store'), $this->payload());

        $budget = Budget::query()->firstOrFail();
        $response->assertRedirect(route('budgets.show', $budget));
        $this->assertSame('NDC/BUD/'.now()->year.'/0001', $budget->code);
        $this->assertSame('draft', $budget->status);
        $this->assertSame('USD', $budget->currency);
        $this->assertSame($user->id, $budget->created_by);
        $this->assertCount(2, $budget->allocations);
        $this->assertEqualsWithDelta(9000.50, $budget->plannedTotal(), 0.001);
        $this->assertDatabaseHas('audit_logs', ['event' => 'created', 'auditable_type' => 'budget', 'auditable_id' => $budget->id]);

        // A second budget receives the next number; an explicit code is kept.
        $this->actingAs($user)->post(route('budgets.store'), $this->payload(['allocations' => []]));
        $this->actingAs($user)->post(route('budgets.store'), $this->payload(['code' => 'ICT-OPS-26', 'allocations' => []]));
        $this->assertTrue(Budget::query()->where('code', 'NDC/BUD/'.now()->year.'/0002')->exists());
        $this->assertTrue(Budget::query()->where('code', 'ICT-OPS-26')->exists());
    }

    public function test_budget_requires_project_department_or_funding_source(): void
    {
        $user = $this->finance();

        $this->actingAs($user)->post(route('budgets.store'), $this->payload(['department_id' => null]))
            ->assertSessionHasErrors('project_id');
        $this->assertSame(0, Budget::query()->count());

        $source = Category::query()->create(['type' => 'funding_source', 'name' => 'Government Budget', 'is_active' => true]);
        $this->actingAs($user)->post(route('budgets.store'), $this->payload(['department_id' => null, 'funding_source_id' => $source->id]))
            ->assertSessionHasNoErrors();

        $project = Project::query()->create(['code' => 'PRJ-1', 'title' => 'Data Centre']);
        $this->actingAs($user)->post(route('budgets.store'), $this->payload(['department_id' => null, 'project_id' => $project->id]))
            ->assertSessionHasNoErrors();

        // A category of the wrong type is not accepted as a funding source.
        $this->actingAs($user)->post(route('budgets.store'), $this->payload(['department_id' => null, 'funding_source_id' => $this->equipment->id]))
            ->assertSessionHasErrors('funding_source_id');
    }

    public function test_fiscal_year_format_is_validated(): void
    {
        $user = $this->finance();

        foreach (['26', '2026-2027', '2026/2028', 'FY2026'] as $bad) {
            $this->actingAs($user)->post(route('budgets.store'), $this->payload(['fiscal_year' => $bad]))
                ->assertSessionHasErrors('fiscal_year');
        }

        $this->actingAs($user)->post(route('budgets.store'), $this->payload(['fiscal_year' => '2026/2027']))
            ->assertSessionHasNoErrors();
    }

    public function test_allocations_cannot_exceed_budget_amount(): void
    {
        $user = $this->finance();

        $this->actingAs($user)->post(route('budgets.store'), $this->payload([
            'amount' => '1000',
            'allocations' => [
                ['category_id' => $this->equipment->id, 'planned_amount' => '600.01'],
                ['category_id' => $this->training->id, 'planned_amount' => '400'],
            ],
        ]))->assertSessionHasErrors('allocations');
        $this->assertSame(0, Budget::query()->count());

        // Exactly the amount is allowed; blank repeater rows are ignored.
        $this->actingAs($user)->post(route('budgets.store'), $this->payload([
            'amount' => '1000',
            'allocations' => [
                ['category_id' => $this->equipment->id, 'planned_amount' => '600.00'],
                ['category_id' => $this->training->id, 'planned_amount' => '400'],
                ['category_id' => '', 'planned_amount' => '', 'description' => ''],
            ],
        ]))->assertSessionHasNoErrors();
        $this->assertCount(2, Budget::query()->firstOrFail()->allocations);
    }

    public function test_draft_budget_update_syncs_allocations(): void
    {
        $user = $this->finance();
        $this->actingAs($user)->post(route('budgets.store'), $this->payload());
        $budget = Budget::query()->firstOrFail();
        [$first, $second] = $budget->allocations->sortBy('id')->values()->all();

        $this->actingAs($user)->put(route('budgets.update', $budget), $this->payload([
            'amount' => '12000',
            'allocations' => [
                ['id' => $first->id, 'category_id' => $this->equipment->id, 'planned_amount' => '7000', 'description' => 'Laptops and printers'],
                ['category_id' => $this->training->id, 'planned_amount' => '2000', 'description' => 'New line'],
            ],
        ]))->assertRedirect(route('budgets.show', $budget));

        $budget->refresh()->load('allocations');
        $this->assertEquals('12000.00', $budget->amount);
        $this->assertCount(2, $budget->allocations);
        $this->assertEquals('7000.00', $first->fresh()->planned_amount);
        $this->assertNull($second->fresh());
    }

    public function test_submit_and_approval_by_a_different_user(): void
    {
        $officer = $this->finance();
        $director = $this->director();
        $budget = Budget::factory()->create(['created_by' => $officer->id, 'department_id' => $this->department->id]);

        $this->actingAs($officer)->post(route('budgets.submit', $budget), ['note' => 'Please approve'])
            ->assertSessionHasNoErrors();
        $this->assertSame('submitted', $budget->fresh()->status);
        $approval = $budget->pendingApproval()->firstOrFail();
        $this->assertSame('budgets.approve', $approval->permission);
        $this->assertDatabaseHas('audit_logs', ['event' => 'submitted', 'auditable_type' => 'budget', 'auditable_id' => $budget->id]);

        // Nothing is approved automatically, and resubmitting is refused.
        $this->actingAs($officer)->post(route('budgets.submit', $budget))->assertForbidden();

        // Finance officer cannot approve (no budgets.approve).
        $this->actingAs($officer)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('submitted', $budget->fresh()->status);

        $this->actingAs($director)->get(route('budgets.show', $budget))->assertOk()->assertSee(__('Approve'));
        $this->actingAs($director)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $budget->fresh()->status);
    }

    public function test_requester_with_approval_rights_cannot_approve_own_budget(): void
    {
        $admin = $this->superAdmin();
        $other = $this->director();
        $budget = Budget::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)->post(route('budgets.submit', $budget));
        $approval = $budget->pendingApproval()->firstOrFail();

        $this->actingAs($admin)->post(route('approvals.decide', $approval), ['decision' => 'approve'])->assertForbidden();
        $this->assertSame('submitted', $budget->fresh()->status);

        $this->actingAs($other)->post(route('approvals.decide', $approval), ['decision' => 'reject', 'decision_comment' => 'Revise the training line.']);
        $this->assertSame('rejected', $budget->fresh()->status);

        // A rejected budget can be edited and resubmitted.
        $this->actingAs($admin)->put(route('budgets.update', $budget), $this->payload(['amount' => '8000', 'allocations' => []]))
            ->assertSessionHasNoErrors();
        $this->assertEquals('8000.00', $budget->fresh()->amount);
        $this->actingAs($admin)->post(route('budgets.submit', $budget))->assertSessionHasNoErrors();
        $this->assertSame('submitted', $budget->fresh()->status);
    }

    public function test_amount_and_allocations_are_locked_after_approval(): void
    {
        $officer = $this->finance();
        $budget = Budget::factory()->approved()->create(['amount' => 5000, 'name' => 'Old name']);

        $this->actingAs($officer)->get(route('budgets.edit', $budget))->assertOk()->assertDontSee('name="amount"', false);

        $this->actingAs($officer)->put(route('budgets.update', $budget), $this->payload([
            'name' => 'New name',
            'amount' => '999999',
            'allocations' => [['category_id' => $this->equipment->id, 'planned_amount' => '100']],
        ]))->assertRedirect(route('budgets.show', $budget));

        $budget->refresh();
        $this->assertSame('New name', $budget->name);
        $this->assertEquals('5000.00', $budget->amount);
        $this->assertSame(0, $budget->allocations()->count());

        // Super Administrators are bound by the same status rules.
        $admin = $this->superAdmin();
        $this->actingAs($admin)->put(route('budgets.update', $budget), $this->payload(['amount' => '1']));
        $this->assertEquals('5000.00', $budget->fresh()->amount);
        $this->actingAs($admin)->post(route('budgets.submit', $budget))->assertForbidden();
        $this->actingAs($admin)->get(route('budgets.edit', Budget::factory()->closed()->create()))->assertForbidden();

        // Submitted and closed budgets cannot be edited at all.
        $submitted = Budget::factory()->submitted()->create();
        $this->actingAs($officer)->get(route('budgets.edit', $submitted))->assertForbidden();
        $this->actingAs($officer)->put(route('budgets.update', $submitted), $this->payload())->assertForbidden();
    }

    public function test_close_budget_requires_approve_permission_and_no_pending_expenses(): void
    {
        $officer = $this->finance();
        $director = $this->director();
        $budget = Budget::factory()->approved()->create();

        $this->actingAs($officer)->post(route('budgets.close', $budget))->assertForbidden();

        $pending = Expense::factory()->create(['budget_id' => $budget->id]);
        $this->actingAs($director)->post(route('budgets.close', $budget))->assertSessionHas('error');
        $this->assertSame('approved', $budget->fresh()->status);

        $pending->forceFill(['status' => 'approved'])->save();
        $this->actingAs($director)->post(route('budgets.close', $budget))->assertSessionHas('success');
        $this->assertSame('closed', $budget->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['event' => 'closed', 'auditable_type' => 'budget', 'auditable_id' => $budget->id]);
    }

    public function test_only_draft_budgets_can_be_deleted_by_budget_deleters(): void
    {
        $officer = $this->finance();
        $admin = $this->superAdmin();
        $draft = Budget::factory()->create();
        $approved = Budget::factory()->approved()->create();

        $this->actingAs($officer)->delete(route('budgets.destroy', $draft))->assertForbidden();
        $this->actingAs($admin)->delete(route('budgets.destroy', $approved))->assertForbidden();
        $this->actingAs($admin)->delete(route('budgets.destroy', $draft))->assertRedirect(route('budgets.index'));
        $this->assertSoftDeleted($draft);
    }

    public function test_authorization_by_role(): void
    {
        $staff = $this->userWithRole('General Staff');
        $budget = Budget::factory()->create();

        foreach (['budgets.index', 'budgets.create', 'budgets.report', 'expenses.index', 'expenses.create', 'expenses.report'] as $route) {
            $this->actingAs($staff)->get(route($route))->assertForbidden();
        }
        $this->actingAs($staff)->get(route('budgets.show', $budget))->assertForbidden();
        $this->actingAs($staff)->post(route('budgets.store'), $this->payload())->assertForbidden();

        // The auditor can read but not create.
        $auditor = $this->userWithRole('Read-Only Auditor');
        $this->actingAs($auditor)->get(route('budgets.index'))->assertOk();
        $this->actingAs($auditor)->get(route('budgets.show', $budget))->assertOk();
        $this->actingAs($auditor)->get(route('budgets.create'))->assertForbidden();
        $this->actingAs($auditor)->put(route('budgets.update', $budget), $this->payload())->assertForbidden();

        // The finance officer creates budgets but cannot approve or close them.
        $officer = $this->finance();
        $this->actingAs($officer)->get(route('budgets.create'))->assertOk();
        $this->assertFalse($officer->can('budgets.approve'));
        $approved = Budget::factory()->approved()->create();
        $this->actingAs($officer)->post(route('budgets.close', $approved))->assertForbidden();
    }

    public function test_index_filters_and_totals(): void
    {
        $user = $this->director();
        $other = Department::query()->create(['name' => 'Finance', 'code' => 'FIN', 'is_active' => true]);

        $a = Budget::factory()->approved()->create(['name' => 'Alpha budget', 'amount' => 1000, 'department_id' => $this->department->id, 'fiscal_year' => '2026']);
        $b = Budget::factory()->approved()->create(['name' => 'Beta budget', 'amount' => 2500.50, 'department_id' => $this->department->id, 'fiscal_year' => '2026']);
        Budget::factory()->create(['name' => 'Gamma budget', 'amount' => 7000, 'department_id' => $other->id, 'fiscal_year' => '2025']);

        Expense::factory()->approved()->create(['budget_id' => $a->id, 'amount' => 200]);
        Expense::factory()->approved()->create(['budget_id' => $b->id, 'amount' => 300.25]);
        Expense::factory()->create(['budget_id' => $b->id, 'amount' => 999]); // pending: not counted
        Expense::factory()->rejected()->create(['budget_id' => $b->id, 'amount' => 888]); // rejected: not counted

        $response = $this->actingAs($user)->get(route('budgets.index', ['department' => $this->department->id]));
        $response->assertOk()->assertSee('Alpha budget')->assertSee('Beta budget')->assertDontSee('Gamma budget');
        $response->assertSee('USD 3,500.50')   // total amount
            ->assertSee('USD 500.25')          // total approved spend
            ->assertSee('USD 3,000.25');       // total remaining

        $this->actingAs($user)->get(route('budgets.index', ['fiscal_year' => '2025']))
            ->assertSee('Gamma budget')->assertDontSee('Alpha budget');
        $this->actingAs($user)->get(route('budgets.index', ['status' => 'draft']))
            ->assertSee('Gamma budget')->assertDontSee('Beta budget');
        $this->actingAs($user)->get(route('budgets.index', ['q' => 'Beta']))
            ->assertSee('Beta budget')->assertDontSee('Alpha budget');
    }

    public function test_budget_pages_render(): void
    {
        $admin = $this->superAdmin();
        $budget = Budget::factory()->approved()->create();
        $budget->allocations()->create(['category_id' => $this->equipment->id, 'planned_amount' => 4000, 'description' => 'Servers']);
        $expense = Expense::factory()->create(['budget_id' => $budget->id, 'category_id' => $this->equipment->id]);
        Expense::factory()->create(['budget_id' => $budget->id, 'category_id' => null]);
        app(ApprovalService::class)->request($expense, 'expenses.approve', $expense->recorder);

        foreach (['budgets.index', 'budgets.create', 'budgets.report', 'expenses.index', 'expenses.create', 'expenses.report'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk();
        }
        $this->actingAs($admin)->get(route('budgets.show', $budget))->assertOk()
            ->assertSee('id="expense-'.$expense->id.'"', false)
            ->assertSee(__('Uncategorised'));
        $this->actingAs($admin)->get(route('budgets.edit', $budget))->assertOk();
        $this->actingAs($admin)->get(route('expenses.show', $expense))->assertOk();
        $this->actingAs($admin)->get(route('expenses.edit', $expense))->assertOk();
        $this->actingAs($admin)->get(route('expenses.create', ['budget' => $budget->id]))->assertOk();

        // Approval inbox links an expense to its detail page.
        $this->assertSame(route('expenses.show', $expense), Approval::query()->firstOrFail()->approvable->approvalUrl());
        $this->actingAs($admin)->get(route('approvals.index'))->assertOk();

        // Somali interface renders as well.
        $admin->forceFill(['locale' => 'so'])->save();
        $this->actingAs($admin)->withSession(['locale' => 'so'])->get(route('budgets.show', $budget))->assertOk();
    }
}
