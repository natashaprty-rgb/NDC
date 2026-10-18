<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\BudgetRequest;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundingSource;
use App\Models\Project;
use App\Services\Finance\BudgetWorkflow;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function __construct(private readonly BudgetWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Budget::class);

        $budgets = Budget::query()
            ->select('budgets.*')
            ->addSelect([
                'allocated_total' => BudgetAllocation::query()->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('budget_id', 'budgets.id'),
                'approved_total' => Expense::query()->selectRaw('COALESCE(SUM(amount), 0)')->whereColumn('budget_id', 'budgets.id')
                    ->whereIn('status', Expense::COMMITTED_STATUSES),
            ])
            ->with(['project', 'department', 'fundingSource'])
            ->when($request->filled('fiscal_year'), fn ($q) => $q->where('fiscal_year', $request->integer('fiscal_year')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->input('status')))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('funding_source_id'), fn ($q) => $q->where('funding_source_id', $request->integer('funding_source_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('title', 'like', $term)->orWhere('reference', 'like', $term));
            })
            ->orderByDesc('fiscal_year')->orderBy('title')
            ->paginate(25)->withQueryString();

        return view('budgets.index', ['budgets' => $budgets] + $this->filterOptions());
    }

    public function create(): View
    {
        $this->authorize('create', Budget::class);

        $budget = new Budget(['fiscal_year' => (int) now()->format('Y'), 'currency' => (string) Settings::get('format.currency', 'USD')]);

        return view('budgets.form', ['budget' => $budget] + $this->formOptions());
    }

    public function store(BudgetRequest $request): RedirectResponse
    {
        $budget = $this->workflow->create($request->validated());

        return redirect()->route('budgets.show', $budget)->with('success', __('budgets.created'));
    }

    public function show(Budget $budget): View
    {
        $this->authorize('view', $budget);

        $budget->load(['project', 'department', 'fundingSource', 'approvedBy', 'creator', 'allocations.category']);
        $categories = $budget->allocations->map(fn (BudgetAllocation $a) => [
            'allocation' => $a,
            'summary' => $budget->summary($a->expense_category_id),
        ]);
        $usedCategoryIds = $budget->allocations->pluck('expense_category_id');

        return view('budgets.show', [
            'budget' => $budget,
            'summary' => $budget->summary(),
            'categories' => $categories,
            'availableCategories' => ExpenseCategory::query()->where('is_active', true)->whereNotIn('id', $usedCategoryIds)->orderBy('name')->get()
                ->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all(),
            'expenses' => $budget->expenses()->with('category')->latest('expense_date')->paginate(25),
        ]);
    }

    public function edit(Budget $budget): View
    {
        $this->authorize('update', $budget);

        return view('budgets.form', ['budget' => $budget] + $this->formOptions());
    }

    public function update(BudgetRequest $request, Budget $budget): RedirectResponse
    {
        $budget->update($request->validated());

        return redirect()->route('budgets.show', $budget)->with('success', __('common.updated'));
    }

    public function destroy(Budget $budget): RedirectResponse
    {
        $this->authorize('delete', $budget);
        $budget->delete();

        return redirect()->route('budgets.index')->with('success', __('common.deleted'));
    }

    public function submit(Budget $budget): RedirectResponse
    {
        $this->authorize('submit', $budget);
        $this->workflow->submit($budget);

        return back()->with('success', __('budgets.submitted'));
    }

    public function approve(Budget $budget, Request $request): RedirectResponse
    {
        $this->authorize('approve', $budget);
        $this->workflow->approve($budget, $request->user());

        return back()->with('success', __('budgets.approved'));
    }

    public function reject(Budget $budget, Request $request): RedirectResponse
    {
        $this->authorize('approve', $budget);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']], [], ['review_note' => __('common.reason')]);
        $this->workflow->reject($budget, $request->user(), $data['review_note']);

        return back()->with('success', __('budgets.rejected'));
    }

    public function close(Budget $budget): RedirectResponse
    {
        $this->authorize('close', $budget);
        $this->workflow->close($budget);

        return back()->with('success', __('budgets.closed'));
    }

    private function formOptions(): array
    {
        return [
            'projects' => Project::query()->notArchived()->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => Department::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all(),
            'fundingSources' => FundingSource::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    private function filterOptions(): array
    {
        return [
            'years' => Budget::query()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year', 'fiscal_year')->all(),
            'projects' => Project::query()->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => Department::query()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all(),
            'fundingSources' => FundingSource::query()->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }
}
