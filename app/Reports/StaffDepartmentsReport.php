<?php

namespace App\Reports;

use App\Models\Staff;
use App\Models\User;

/** Employment details (status, type, dates) appear only with staff.view_sensitive. */
class StaffDepartmentsReport extends Report
{
    public function key(): string
    {
        return 'staff-departments';
    }

    public function group(): string
    {
        return 'operations';
    }

    public function permissions(): array
    {
        return ['staff.view'];
    }

    public function sensitive(): bool
    {
        return true;
    }

    public function filters(User $user): array
    {
        return ['department_id' => $this->departmentFilter()];
    }

    public function build(User $user, array $filters): ReportResult
    {
        $sensitive = $user->can('staff.view_sensitive');
        $staff = Staff::query()->with('department')
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->orderBy('full_name')->get();
        $current = $staff->where('employment_status', '!=', 'separated');

        $headcount = [];
        foreach ($current->groupBy(fn ($s) => $s->department?->displayName() ?? __('reports.unassigned'))->sortKeys() as $dept => $members) {
            $row = [$dept, $members->count()];
            if ($sensitive) {
                foreach (['active', 'on_leave', 'suspended'] as $status) {
                    $row[] = $members->where('employment_status', $status)->count();
                }
            }
            $headcount[] = $row;
        }
        $headcountCols = [$this->col('common.department'), $this->col('reports.c.headcount', 'int')];
        if ($sensitive) {
            foreach (['active', 'on_leave', 'suspended'] as $status) {
                $headcountCols[] = ['label' => status_label($status), 'type' => 'int'];
            }
        }

        $listCols = [$this->col('reports.c.staff_number'), $this->col('common.name'), $this->col('reports.c.job_title'),
            $this->col('common.department'), $this->col('reports.c.email'), $this->col('reports.c.phone')];
        if ($sensitive) {
            array_push($listCols, $this->col('reports.c.employment_status'), $this->col('reports.c.employment_type'), $this->col('reports.c.start_date', 'date'));
        }
        $rows = [];
        $links = [];
        foreach ($staff as $i => $s) {
            $row = [$s->staff_number, $s->full_name, $s->job_title, $s->department?->displayName(), $s->official_email, $s->official_phone];
            if ($sensitive) {
                array_push($row, status_label($s->employment_status), status_label($s->employment_type), fmt_date($s->start_date, ''));
            }
            $rows[] = $row;
            $links[$i] = route_if('staff.show', $s);
        }

        return (new ReportResult)
            ->addSummary(__('reports.s.current_staff'), $current->count())
            ->addSummary(__('reports.s.departments'), count($headcount))
            ->addTable(new ReportTable(__('reports.t.headcount'), $headcountCols, $headcount))
            ->addTable(new ReportTable(__('reports.t.staff_list'), $listCols, $rows, $links));
    }
}
