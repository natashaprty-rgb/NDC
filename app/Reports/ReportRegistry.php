<?php

namespace App\Reports;

use App\Models\User;

final class ReportRegistry
{
    /** @var array<int, class-string<Report>> */
    private const REPORTS = [
        ProjectProgressReport::class,
        TasksReport::class,
        StaffDepartmentsReport::class,
        DocumentsReport::class,
        CorrespondenceReport::class,
        MeetingsReport::class,
        AssetsReport::class,
        ProcurementReport::class,
        BudgetExpenditureReport::class,
        PendingApprovalsReport::class,
        AuditActivityReport::class,
    ];

    public const GROUPS = ['operations', 'records', 'resources', 'finance', 'administration'];

    /** @return array<string, Report> */
    public function all(): array
    {
        $reports = [];
        foreach (self::REPORTS as $class) {
            $report = app($class);
            $reports[$report->key()] = $report;
        }

        return $reports;
    }

    public function find(string $key): ?Report
    {
        return $this->all()[$key] ?? null;
    }

    /** @return array<string, array<string, Report>> group => reports the user may run */
    public function availableFor(User $user): array
    {
        $grouped = array_fill_keys(self::GROUPS, []);
        foreach ($this->all() as $key => $report) {
            if ($report->canRun($user)) {
                $grouped[$report->group()][$key] = $report;
            }
        }

        return array_filter($grouped);
    }
}
