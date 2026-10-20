<?php

namespace App\Reports;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Support\Status;
use Illuminate\Http\Request;

class BudgetReport extends Report
{
    public function key(): string
    {
        return 'budgets';
    }

    public function title(): string
    {
        return __('Budget allocation and expenditure summary');
    }

    public function description(): string
    {
        return __('Approved budgets compared with planned and recorded expenditure.');
    }

    public function permission(): string
    {
        return 'budgets.view';
    }

    public function filters(): array
    {
        return [
            'fiscal_year' => ['label' => 'Fiscal year', 'type' => 'select', 'options' => Budget::query()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year', 'fiscal_year')->all()],
            'status' => ['label' => 'Status', 'type' => 'select', 'options' => Status::options('budget')],
            'project' => ['label' => 'Project', 'type' => 'select', 'options' => Project::options()],
            'department' => ['label' => 'Department', 'type' => 'select', 'options' => Department::options()],
            'funding_source' => ['label' => 'Funding source', 'type' => 'select', 'options' => Category::options('funding_source')],
        ];
    }

    public function columns(): array
    {
        return ['Code', 'Budget', 'Fiscal year', 'Project / department', 'Funding source', 'Status', 'Budget amount', 'Planned', 'Approved spend', 'Pending', 'Remaining', 'Used %'];
    }

    public function numericColumns(): array
    {
        return [6, 7, 8, 9, 10, 11];
    }

    public function rows(Request $request, User $user): iterable
    {
        return Budget::query()->with(['project', 'department', 'fundingSource'])
            ->when($request->query('fiscal_year'), fn ($q, $y) => $q->where('fiscal_year', $y))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('project'), fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->query('department'), fn ($q, $d) => $q->where('department_id', $d))
            ->when($request->query('funding_source'), fn ($q, $f) => $q->where('funding_source_id', $f))
            ->orderBy('fiscal_year')->orderBy('code')->get()
            ->map(fn (Budget $b) => [
                $b->code, $b->name, $b->fiscal_year,
                $b->project?->code ?? $b->department?->displayName(), $b->fundingSource?->displayName(),
                Status::label('budget', $b->status), money($b->amount, $b->currency), money($b->plannedTotal(), $b->currency),
                money($b->approvedSpend(), $b->currency), money($b->pendingSpend(), $b->currency), money($b->remaining(), $b->currency),
                $b->utilisation(),
            ]);
    }
}
