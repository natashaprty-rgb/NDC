<?php

namespace Tests\Feature\Reports;

use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\Staff;
use App\Models\Task;
use App\Models\User;
use App\Reports\BudgetExpenditureReport;
use App\Reports\DocumentsReport;
use App\Reports\ReportExporter;
use App\Reports\ReportRegistry;
use App\Reports\TasksReport;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    public function test_index_lists_only_reports_the_user_may_run(): void
    {
        $officer = $this->userWithRole('project-officer');
        $director = $this->userWithRole('director');

        $this->actingAs($officer)->get('/reports')->assertOk()
            ->assertSee(__('reports.r.project-progress.title'))
            ->assertDontSee(__('reports.r.budget-expenditure.title'))
            ->assertDontSee(__('reports.r.audit-activity.title'));

        $this->actingAs($director)->get('/reports')->assertOk()
            ->assertSee(__('reports.r.budget-expenditure.title'))
            ->assertSee(__('reports.r.audit-activity.title'));
    }

    public function test_users_without_permission_are_refused(): void
    {
        $staff = $this->userWithRole('staff');            // no reports.view at all
        $officer = $this->userWithRole('project-officer'); // reports.view but not reports.financial

        $this->actingAs($staff)->get('/reports')->assertForbidden();
        $this->actingAs($staff)->get('/reports/budget-expenditure')->assertForbidden();
        $this->actingAs($officer)->get('/reports/budget-expenditure')->assertForbidden();
        $this->actingAs($officer)->get('/reports/budget-expenditure/export?format=csv')->assertForbidden();
    }

    public function test_export_requires_export_permission(): void
    {
        $officer = $this->userWithRole('project-officer');

        $this->actingAs($officer)->get('/reports/project-progress')->assertOk();
        $this->actingAs($officer)->get('/reports/project-progress/export?format=csv')->assertForbidden();
        $this->assertFalse(AuditLog::query()->where('event', 'exported')->exists());
    }

    public function test_unknown_report_or_format_returns_404(): void
    {
        $director = $this->userWithRole('director');

        $this->actingAs($director)->get('/reports/does-not-exist')->assertNotFound();
        $this->actingAs($director)->get('/reports/does-not-exist/export')->assertNotFound();
        $this->actingAs($director)->get('/reports/project-progress/export?format=xlsx')->assertNotFound();
    }

    public function test_csv_contains_headings_and_exact_database_rows_and_is_audited(): void
    {
        $director = $this->userWithRole('director');
        $this->actingAs($director);
        $project = Project::query()->create(['reference' => 'NDC-PRJ-2026-0001', 'name' => 'Office Network Upgrade', 'status' => 'in_progress', 'manager_id' => $director->id]);
        foreach (['completed', 'completed', 'todo', 'cancelled'] as $i => $status) {
            Task::query()->create(['reference' => 'T-'.$i, 'project_id' => $project->id, 'title' => 'Task '.$i, 'status' => $status]);
        }

        $response = $this->get('/reports/project-progress/export?format=csv');
        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Reference,Name,Status,Manager', $csv);
        // 3 non-cancelled tasks, 2 completed => 66 %
        $this->assertStringContainsString('NDC-PRJ-2026-0001,"Office Network Upgrade","In progress"', $csv);
        $this->assertStringContainsString($director->name, $csv);
        $this->assertMatchesRegularExpression('/NDC-PRJ-2026-0001,.*,3,2,0,66,0/', $csv);

        $log = AuditLog::query()->where('event', 'exported')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($director->id, $log->user_id);
        $this->assertSame('project-progress', $log->new_values['report']);
        $this->assertSame('csv', $log->new_values['format']);
    }

    public function test_csv_neutralises_formula_injection_but_keeps_negative_numbers(): void
    {
        $director = $this->userWithRole('director');
        $this->actingAs($director);
        Project::query()->create(['reference' => 'P-1', 'name' => '=HYPERLINK("http://evil.example","click")', 'status' => 'planned']);

        $csv = $this->get('/reports/project-progress/export?format=csv')->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK', $csv);

        $this->assertSame("'@SUM(A1)", ReportExporter::sanitizeCell('@SUM(A1)'));
        $this->assertSame("'+cmd", ReportExporter::sanitizeCell('+cmd'));
        $this->assertSame('-250.10', ReportExporter::sanitizeCell('-250.10'));
        $this->assertSame('Normal text', ReportExporter::sanitizeCell('Normal text'));
    }

    public function test_documents_report_respects_confidentiality_rules(): void
    {
        $officer = $this->userWithRole('project-officer'); // documents.view only
        $own = $this->placeInDepartment($officer);
        $other = Department::factory()->create();
        $author = User::factory()->create();

        $make = fn (string $title, string $level, Department $dept) => Document::query()->create([
            'reference' => 'DOC-'.$title, 'title' => $title, 'confidentiality' => $level, 'department_id' => $dept->id,
            'owner_id' => $author->id, 'registration_date' => today(), 'status' => 'approved',
        ]);
        $make('InternalCircular', 'internal', $other);
        $make('OwnDeptConfidential', 'confidential', $own);
        $make('OtherDeptConfidential', 'confidential', $other);
        $make('RestrictedMemo', 'restricted', $own);

        $this->actingAs($officer)->get('/reports/documents')->assertOk()->assertSee('2', false);

        $result = app(DocumentsReport::class)->build($officer, []);
        $this->assertSame(2, $result->summary[0]['value']);
        $byConfidentiality = collect($result->tables[2]->rows)->pluck(1, 0)->all();
        $this->assertSame([status_label('internal') => 1, status_label('confidential') => 1], $byConfidentiality);

        $director = $this->userWithRole('director'); // view_confidential + view_restricted
        $this->assertSame(4, app(DocumentsReport::class)->build($director, [])->summary[0]['value']);
    }

    public function test_budget_report_figures_are_exact(): void
    {
        $director = $this->userWithRole('director');
        $recorder = $this->userWithRole('finance-officer');
        $this->actingAs($director);
        $category = ExpenseCategory::query()->create(['code' => 'EQP', 'name' => 'Equipment']);
        $budget = Budget::query()->create(['reference' => 'BGT-1', 'title' => 'ICT budget', 'fiscal_year' => 2026, 'status' => 'approved']);
        BudgetAllocation::query()->create(['budget_id' => $budget->id, 'expense_category_id' => $category->id, 'amount' => '1000.00']);
        $expense = fn (string $ref, string $amount, string $status) => Expense::query()->create([
            'reference' => $ref, 'budget_id' => $budget->id, 'expense_category_id' => $category->id, 'description' => 'Item '.$ref,
            'amount' => $amount, 'expense_date' => '2026-03-01', 'status' => $status, 'recorded_by' => $recorder->id,
        ]);
        $expense('E1', '250.10', 'approved');
        $expense('E2', '50.05', 'recorded');
        $expense('E3', '99.99', 'rejected');

        $result = app(BudgetExpenditureReport::class)->build($director, ['fiscal_year' => 2026]);
        $row = $result->tables[0]->rows[0];

        $this->assertSame(['1000.00', '300.15', '250.10', '0.00', '749.90'], array_slice($row, 5, 5));
        $this->assertSame('749.90', $result->tables[0]->totals[9]);
        $this->assertSame('749.90', $result->summary[3]['value']);

        $csv = $this->get('/reports/budget-expenditure/export?format=csv&fiscal_year=2026')->streamedContent();
        $this->assertStringContainsString('1000.00,300.15,250.10,0.00,749.90', $csv);

        $this->get('/reports/budget-expenditure?fiscal_year=2026')->assertOk()->assertSee('749.90');
    }

    public function test_pdf_export_returns_pdf_document(): void
    {
        $director = $this->userWithRole('director');
        $this->actingAs($director);
        Project::query()->create(['reference' => 'P-1', 'name' => 'Records Digitisation', 'status' => 'planned']);

        $response = $this->get('/reports/project-progress/export?format=pdf');
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->assertTrue(AuditLog::query()->where('event', 'exported')->where('description', 'like', '%(pdf)%')->exists());
    }

    public function test_tasks_report_is_limited_to_own_tasks_without_view_all(): void
    {
        $officer = $this->userWithRole('project-officer');
        $other = User::factory()->create();
        Task::query()->create(['reference' => 'T-MINE', 'title' => 'Mine', 'assigned_to' => $officer->id, 'status' => 'todo', 'due_date' => today()->subDay()]);
        Task::query()->create(['reference' => 'T-OTHER', 'title' => 'Other', 'assigned_to' => $other->id, 'status' => 'todo']);

        $result = app(TasksReport::class)->build($officer, []);
        $this->assertSame(['T-MINE'], array_column($result->tables[1]->rows, 0));
        $this->assertSame(1, $result->summary[2]['value']); // overdue

        $director = $this->userWithRole('director');
        $this->assertCount(2, app(TasksReport::class)->build($director, [])->tables[1]->rows);
    }

    public function test_staff_report_hides_employment_details_without_sensitive_permission(): void
    {
        $officer = $this->userWithRole('project-officer');
        $director = $this->userWithRole('director');
        Staff::factory()->create(['full_name' => 'Listed Person', 'employment_status' => 'on_leave']);

        $this->actingAs($officer)->get('/reports/staff-departments')->assertOk()
            ->assertSee('Listed Person')->assertDontSee(__('reports.c.employment_status'));
        $this->actingAs($director)->get('/reports/staff-departments')->assertOk()
            ->assertSee(__('reports.c.employment_status'))->assertSee(status_label('on_leave'));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $director = $this->userWithRole('director');

        $this->actingAs($director)->get('/reports/tasks?date_from=2026-05-01&date_to=2026-04-01')->assertSessionHasErrors('date_to');
        $this->actingAs($director)->get('/reports/tasks?status=bogus')->assertSessionHasErrors('status');
    }

    public function test_every_report_renders_for_an_administrator(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $admin = $this->userWithRole('super-admin');
        $this->actingAs($admin);
        Project::query()->create(['reference' => 'P-1', 'name' => 'Sample', 'status' => 'in_progress']);

        foreach (array_keys(app(ReportRegistry::class)->all()) as $key) {
            $this->get('/reports/'.$key)->assertOk();
            $this->get('/reports/'.$key.'/export?format=csv')->assertOk();
        }
    }

    public function test_exports_are_rate_limited(): void
    {
        $director = $this->userWithRole('director');
        $this->actingAs($director);

        for ($i = 0; $i < 10; $i++) {
            $this->get('/reports/pending-approvals/export?format=csv')->assertOk();
        }
        $this->get('/reports/pending-approvals/export?format=csv')->assertStatus(429);
    }
}
