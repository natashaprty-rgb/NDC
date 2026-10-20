<?php

namespace Tests\Feature\Reports;

use App\Models\Department;
use App\Models\Document;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Reports\ReportRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function seedRecords(User $owner): void
    {
        $dept = Department::query()->create(['name' => 'ICT', 'code' => 'ICT']);
        $project = Project::query()->create([
            'code' => 'PRJ-1', 'title' => 'Facility construction', 'status' => 'in_progress', 'department_id' => $dept->id,
            'expected_end_date' => today()->subDay(), 'progress' => 40, 'planned_budget' => 1000,
        ]);
        Task::query()->create(['title' => 'Overdue survey', 'project_id' => $project->id, 'assigned_to' => $owner->id, 'status' => 'pending', 'due_date' => today()->subDays(3)]);
        Document::query()->create(['reference_number' => 'NDC/DOC/2026/0001', 'title' => 'Secret plan', 'confidentiality' => 'confidential', 'status' => 'draft', 'owner_id' => $owner->id]);
    }

    public function test_every_report_renders_and_exports_for_super_admin(): void
    {
        $admin = $this->superAdmin();
        $this->seedRecords($admin);

        $this->actingAs($admin)->get(route('reports.index'))->assertOk();

        foreach (app(ReportRegistry::class)->all() as $report) {
            $this->actingAs($admin)->get(route('reports.show', $report->key()))->assertOk()->assertSee($report->title());
            $this->actingAs($admin)->get(route('reports.csv', $report->key()))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        }

        $pdf = $this->actingAs($admin)->get(route('reports.pdf', 'projects'));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_report_data_comes_from_records(): void
    {
        $admin = $this->superAdmin();
        $this->seedRecords($admin);

        $this->actingAs($admin)->get(route('reports.show', ['key' => 'projects', 'delayed' => 1]))
            ->assertSee('Facility construction');
        $this->actingAs($admin)->get(route('reports.show', ['key' => 'pending', 'kind' => 'overdue']))
            ->assertSee('Overdue survey');
    }

    public function test_reports_respect_record_level_visibility(): void
    {
        $owner = $this->superAdmin();
        $this->seedRecords($owner);
        $viewer = $this->userWithPermissions(['reports.view', 'reports.export', 'documents.view']);

        $this->actingAs($viewer)->get(route('reports.show', 'documents'))->assertOk()->assertDontSee('Secret plan');
        $csv = $this->actingAs($viewer)->get(route('reports.csv', 'documents'))->streamedContent();
        $this->assertStringNotContainsString('Secret plan', $csv);
    }

    public function test_module_permission_is_required_per_report(): void
    {
        $viewer = $this->userWithPermissions(['reports.view']);

        $this->actingAs($viewer)->get(route('reports.show', 'budgets'))->assertForbidden();
        $this->actingAs($viewer)->get(route('reports.show', 'audit'))->assertForbidden();
    }

    public function test_export_requires_export_permission(): void
    {
        $viewer = $this->userWithPermissions(['reports.view', 'projects.view']);

        $this->actingAs($viewer)->get(route('reports.show', 'projects'))->assertOk();
        $this->actingAs($viewer)->get(route('reports.csv', 'projects'))->assertForbidden();
        $this->actingAs($viewer)->get(route('reports.pdf', 'projects'))->assertForbidden();
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->get(route('reports.show', ['key' => 'projects', 'status' => "x' OR 1=1"]))->assertSessionHasErrors('status');
    }

    public function test_csv_cells_are_protected_against_formula_injection(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", \App\Support\CsvExport::cell('=HYPERLINK("x")'));
        $this->assertSame('-15', \App\Support\CsvExport::cell('-15'));
    }

    public function test_general_staff_cannot_open_reports(): void
    {
        $this->actingAs($this->userWithRole('General Staff'))->get(route('reports.index'))->assertForbidden();
    }
}
