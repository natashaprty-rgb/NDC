<?php

namespace App\Http\Controllers\Budgets;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Project;
use App\Support\Status;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable budget summary (allocation against actual per budget) and
 * expenditure report. CSV/PDF exports are provided by the Reports module.
 */
class BudgetReportController extends Controller
{
    /** Upper bound on budgets in one printable summary. */
    private const MAX_BUDGETS = 100;

    /** Upper bound on expense lines in one printable report. */
    private const MAX_EXPENSES = 1000;

    public function budgets(Request $request): View
    {
        $this->authorize('viewAny', Budget::class);

        $filters = $request->only(BudgetController::FILTERS);
        $query = Budget::query()->filter($filters)->with(['project', 'department', 'fundingSource'])
            ->orderBy('fiscal_year', 'desc')->orderBy('code');

        $total = (clone $query)->count();
        $budgets = $query->limit(self::MAX_BUDGETS)->get()->map(fn (Budget $budget) => [
            'budget' => $budget,
            'summary' => $budget->spendSummary(),
            'breakdown' => $budget->categoryBreakdown(),
        ]);

        return view('budgets.report', [
            'rows' => $budgets,
            'total' => $total,
            'limit' => self::MAX_BUDGETS,
            'filterLabels' => $this->budgetFilterLabels($filters),
        ]);
    }

    public function expenses(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $filters = $request->only(ExpenseController::FILTERS);
        $query = Expense::query()->filter($filters)->with(['budget', 'category', 'recorder'])
            ->orderBy('expense_date')->orderBy('id');

        $count = (clone $query)->count();
        $expenses = $query->limit(self::MAX_EXPENSES)->get();

        $byCategory = Expense::query()->filter($filters)
            ->join('budgets', 'budgets.id', '=', 'expenses.budget_id')
            ->selectRaw('budgets.currency as currency, expenses.category_id as category_id, expenses.status as status, SUM(expenses.amount) as total, COUNT(*) as line_count')
            ->groupBy('budgets.currency', 'expenses.category_id', 'expenses.status')
            ->get();

        $names = Category::query()->whereIn('id', $byCategory->pluck('category_id')->filter())->get()
            ->mapWithKeys(fn (Category $c) => [$c->id => $c->displayName()]);

        $categoryRows = $byCategory->groupBy(fn ($row) => $row->currency.'|'.$row->category_id)
            ->map(function ($rows) use ($names) {
                $first = $rows->first();
                $cents = fn (string $status) => Budget::cents($rows->firstWhere('status', $status)?->total ?? 0);

                return [
                    'currency' => $first->currency,
                    'category' => $first->category_id ? ($names[$first->category_id] ?? __('Unknown category')) : __('Uncategorised'),
                    'lines' => (int) $rows->sum('line_count'),
                    'approved' => $cents('approved') / 100,
                    'pending' => $cents('pending') / 100,
                    'rejected' => $cents('rejected') / 100,
                ];
            })
            ->sortBy([['currency', 'asc'], ['category', 'asc']])->values();

        return view('expenses.report', [
            'expenses' => $expenses,
            'count' => $count,
            'limit' => self::MAX_EXPENSES,
            'categoryRows' => $categoryRows,
            'totals' => ExpenseController::totals(Expense::query()->filter($filters)),
            'filterLabels' => $this->expenseFilterLabels($filters),
        ]);
    }

    /**
     * Human-readable description of the filters applied, printed on the report.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function budgetFilterLabels(array $filters): array
    {
        return array_filter([
            __('Search') => $filters['q'] ?? null,
            __('Fiscal year') => $filters['fiscal_year'] ?? null,
            __('Project') => $this->projectLabel($filters['project'] ?? null),
            __('Department') => isset($filters['department']) ? Department::query()->find((int) $filters['department'])?->displayName() : null,
            __('Funding source') => isset($filters['funding_source']) ? Category::query()->find((int) $filters['funding_source'])?->displayName() : null,
            __('Status') => in_array($filters['status'] ?? null, Status::keys('budget'), true) ? Status::label('budget', $filters['status']) : null,
        ], fn ($v) => filled($v));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    private function expenseFilterLabels(array $filters): array
    {
        $budget = isset($filters['budget']) ? Budget::query()->find((int) $filters['budget']) : null;

        return array_filter([
            __('Search') => $filters['q'] ?? null,
            __('Budget') => $budget ? $budget->code.' — '.$budget->name : null,
            __('Expense category') => isset($filters['category']) ? Category::query()->find((int) $filters['category'])?->displayName() : null,
            __('Status') => in_array($filters['status'] ?? null, Status::keys('expense'), true) ? Status::label('expense', $filters['status']) : null,
            __('From') => $this->dateLabel($filters['from'] ?? null),
            __('To') => $this->dateLabel($filters['to'] ?? null),
            __('Project') => $this->projectLabel($filters['project'] ?? null),
        ], fn ($v) => filled($v));
    }

    private function projectLabel(mixed $id): ?string
    {
        $project = filled($id) ? Project::query()->find((int) $id) : null;

        return $project ? $project->code.' — '.$project->title : null;
    }

    private function dateLabel(mixed $value): ?string
    {
        $date = is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        return $date && $date->format('Y-m-d') === $value ? fmt_date($value) : null;
    }
}
