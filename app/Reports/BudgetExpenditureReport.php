<?php

namespace App\Reports;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\Money;

/**
 * Budget versus expenditure per budget and expense category.
 * recorded = every non-rejected expense; approved = approved + paid + reconciled;
 * paid = paid + reconciled; remaining = allocated - approved. Exact integer-cent arithmetic.
 */
class BudgetExpenditureReport extends Report
{
    public function key(): string
    {
        return 'budget-expenditure';
    }

    public function group(): string
    {
        return 'finance';
    }

    public function permissions(): array
    {
        return ['reports.financial', 'budgets.view'];
    }

    public function filters(User $user): array
    {
        $years = Budget::query()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year')->mapWithKeys(fn ($y) => [$y => (string) $y])->all();

        return [
            'fiscal_year' => ['type' => 'select', 'label' => __('reports.f.fiscal_year'), 'options' => $years],
            'project_id' => $this->projectFilter(),
            'department_id' => $this->departmentFilter(),
            'status' => $this->statusFilter(Budget::STATUSES),
        ];
    }

    public function build(User $user, array $filters): ReportResult
    {
        $budgets = Budget::query()
            ->when(! empty($filters['fiscal_year']), fn ($q) => $q->where('fiscal_year', $filters['fiscal_year']))
            ->when(! empty($filters['project_id']), fn ($q) => $q->where('project_id', $filters['project_id']))
            ->when(! empty($filters['department_id']), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->orderBy('fiscal_year')->orderBy('reference')->get()->keyBy('id');

        $ids = $budgets->keys();
        $allocations = BudgetAllocation::query()->whereIn('budget_id', $ids)
            ->selectRaw('budget_id, expense_category_id, SUM(amount) as total')->groupBy('budget_id', 'expense_category_id')->get();
        $expenses = Expense::query()->whereIn('budget_id', $ids)
            ->selectRaw('budget_id, expense_category_id, status, SUM(amount) as total')->groupBy('budget_id', 'expense_category_id', 'status')->get();
        $categories = ExpenseCategory::query()->get()->keyBy('id');

        // [budget][category] => cents per measure
        $matrix = [];
        $blank = ['allocated' => 0, 'recorded' => 0, 'approved' => 0, 'paid' => 0];
        foreach ($allocations as $a) {
            $matrix[$a->budget_id][$a->expense_category_id] ??= $blank;
            $matrix[$a->budget_id][$a->expense_category_id]['allocated'] += Money::toCents((string) $a->total);
        }
        foreach ($expenses as $e) {
            $cents = Money::toCents((string) $e->total);
            $cell = &$matrix[$e->budget_id][$e->expense_category_id];
            $cell ??= $blank;
            if ($e->status !== 'rejected') {
                $cell['recorded'] += $cents;
            }
            if (in_array($e->status, Expense::COMMITTED_STATUSES, true)) {
                $cell['approved'] += $cents;
            }
            if (in_array($e->status, ['paid', 'reconciled'], true)) {
                $cell['paid'] += $cents;
            }
            unset($cell);
        }

        $rows = [];
        $links = [];
        $totals = $blank;
        foreach ($budgets as $budget) {
            foreach ($matrix[$budget->id] ?? [] as $categoryId => $cell) {
                $links[count($rows)] = route_if('budgets.show', $budget);
                $rows[] = [
                    $budget->reference, $budget->title, $budget->fiscal_year, $categories->get($categoryId)?->displayName() ?? '—', status_label($budget->status),
                    Money::fromCents($cell['allocated']), Money::fromCents($cell['recorded']), Money::fromCents($cell['approved']),
                    Money::fromCents($cell['paid']), Money::fromCents($cell['allocated'] - $cell['approved']),
                ];
                foreach ($cell as $k => $v) {
                    $totals[$k] += $v;
                }
            }
        }

        $remaining = $totals['allocated'] - $totals['approved'];
        $table = new ReportTable(__('reports.t.budget_vs_expenditure'), [
            $this->col('common.reference'), $this->col('common.title'), $this->col('reports.f.fiscal_year'), $this->col('common.category'), $this->col('common.status'),
            $this->col('reports.c.allocated', 'money'), $this->col('reports.c.recorded', 'money'), $this->col('reports.c.approved', 'money'),
            $this->col('reports.c.paid', 'money'), $this->col('reports.c.remaining', 'money'),
        ], $rows, $links, $rows === [] ? null : [
            __('common.total'), '', '', '', '', Money::fromCents($totals['allocated']), Money::fromCents($totals['recorded']),
            Money::fromCents($totals['approved']), Money::fromCents($totals['paid']), Money::fromCents($remaining),
        ]);

        return (new ReportResult)
            ->addSummary(__('reports.c.allocated'), Money::fromCents($totals['allocated']), 'money')
            ->addSummary(__('reports.c.recorded'), Money::fromCents($totals['recorded']), 'money')
            ->addSummary(__('reports.c.approved'), Money::fromCents($totals['approved']), 'money')
            ->addSummary(__('reports.c.remaining'), Money::fromCents($remaining), 'money')
            ->addTable($table);
    }
}
