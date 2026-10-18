<?php

namespace Tests\Feature\Foundation;

use App\Models\Project;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_empty_database_shows_empty_states_not_invented_figures(): void
    {
        $director = $this->userWithRole('director');

        $this->actingAs($director)->get('/dashboard')
            ->assertOk()
            ->assertSee(__('dashboard.no_projects'))
            ->assertSee(__('dashboard.no_approved_budgets'))
            ->assertSee(__('dashboard.no_meetings'));
    }

    public function test_project_counts_come_from_database_records(): void
    {
        $director = $this->userWithRole('director');
        $this->actingAs($director);
        foreach (['in_progress', 'in_progress', 'planned', 'completed'] as $i => $status) {
            Project::query()->create(['reference' => 'P-'.$i, 'name' => 'Project '.$i, 'status' => $status]);
        }
        Project::query()->create(['reference' => 'P-arch', 'name' => 'Archived', 'status' => 'in_progress', 'archived_at' => now()]);

        $metrics = app(\App\Services\DashboardMetrics::class)->for($director);

        $this->assertSame(4, $metrics['projects']['total']);
        $this->assertSame(2, $metrics['projects']['active']);
        $this->assertSame(1, $metrics['projects']['by_status']['planned']);
    }

    public function test_blocks_are_hidden_without_permission(): void
    {
        $staff = $this->userWithRole('staff');
        $metrics = app(\App\Services\DashboardMetrics::class)->for($staff);

        $this->assertArrayNotHasKey('budget', $metrics);
        $this->assertArrayNotHasKey('tasks', $metrics);
        $this->assertArrayNotHasKey('assets', $metrics);
    }
}
