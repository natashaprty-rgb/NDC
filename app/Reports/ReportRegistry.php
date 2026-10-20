<?php

namespace App\Reports;

use App\Models\User;

class ReportRegistry
{
    /** @var list<class-string<Report>> */
    private const REPORTS = [
        ProjectProgressReport::class,
        MilestoneReport::class,
        TaskAssignmentReport::class,
        DocumentRegisterReport::class,
        CorrespondenceReport::class,
        MeetingActionsReport::class,
        AssetInventoryReport::class,
        ProcurementReport::class,
        BudgetReport::class,
        PendingActivitiesReport::class,
        AuditActivityReport::class,
    ];

    /**
     * @return list<Report>
     */
    public function all(): array
    {
        return array_map(fn (string $class) => app($class), self::REPORTS);
    }

    /**
     * @return list<Report>
     */
    public function availableTo(User $user): array
    {
        return array_values(array_filter($this->all(), fn (Report $r) => $user->can($r->permission())));
    }

    public function find(string $key): ?Report
    {
        foreach ($this->all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }
}
