<?php

namespace App\Http\Controllers\Budgets;

use App\Http\Controllers\Budgets\Concerns\AuthorizesWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budgets\BudgetRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Project;
use App\Services\ApprovalService;
use App\Support\Audit;
use App\Support\ReferenceNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Budget register: approved budgets per project, department or funding
 * source, their planned allocations and the approval/closure workflow.
 * Monitoring only — the system makes no payments.
 */
class BudgetController extends Controller
{
    use AuthorizesWorkflow;

    public const FILTERS = ['q', 'fiscal_year', 'project', 'department', 'funding_source', 'status'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Budget::class);

        $filters = $request->only(self::FILTERS);
        $query = Budget::query()->filter($filters)
            ->with(['project', 'department', 'fundingSource'])
            ->withSum(['expenses as approved_spend' => fn (Builder $q) => $q->reorder()->where('status', 'approved')], 'amount')
            ->withSum(['expenses as pending_spend' => fn (Builder $q) => $q->reorder()->where('status', 'pending')], 'amount');

        $this->applySort($query, $request, ['code', 'name', 'fiscal_year', 'amount', 'status', 'created_at'], 'created_at');

        return view('budgets.index', [
            'budgets' => $query->paginate($this->perPage($request))->withQueryString(),
            'totals' => $this->totals($filters),
            ...$this->filterOptions(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Budget::class);

        return view('budgets.form', $this->formData(new Budget([
            'currency' => setting('currency', 'USD'),
            'fiscal_year' => now()->format('Y'),
            'status' => 'draft',
        ])));
    }

    public function store(BudgetRequest $request): RedirectResponse
    {
        $this->authorize('create', Budget::class);
        $data = $request->validated();

        $budget = DB::transaction(function () use ($data, $request) {
            $budget = Budget::query()->create([
                ...collect($data)->except(['allocations', 'code'])->all(),
                'code' => filled($data['code'] ?? null) ? $data['code'] : ReferenceNumber::next('BUD'),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
            $this->syncAllocations($budget, $data['allocations'] ?? []);

            return $budget;
        });

        return redirect()->route('budgets.show', $budget)->with('success', __('Budget :code created as a draft. Submit it for approval when ready.', ['code' => $budget->code]));
    }

    public function show(Budget $budget): View
    {
        $this->authorize('view', $budget);

        $budget->load(['project', 'department', 'fundingSource', 'creator', 'allocations.category', 'attachments.uploader']);

        return view('budgets.show', [
            'budget' => $budget,
            'summary' => $budget->spendSummary(),
            'breakdown' => $budget->categoryBreakdown(),
            'expenses' => $budget->expenses()->with(['category', 'recorder'])->latest('id')
                ->paginate(20, ['*'], 'expenses_page')->withQueryString(),
        ]);
    }

    public function edit(Budget $budget): View
    {
        $this->authorizeWorkflow('update', $budget);

        return view('budgets.form', $this->formData($budget->load('allocations')));
    }

    public function update(BudgetRequest $request, Budget $budget): RedirectResponse
    {
        $this->authorizeWorkflow('update', $budget);
        $data = $request->validated();

        if (! $request->changesAmounts()) {
            // Approved budgets: only the descriptive fields can change.
            $budget->update(collect($data)->only(['name', 'notes'])->all());

            return redirect()->route('budgets.show', $budget)->with('success', __('Budget updated.'));
        }

        DB::transaction(function () use ($budget, $data) {
            $budget->update([
                ...collect($data)->except(['allocations', 'code'])->all(),
                'code' => filled($data['code'] ?? null) ? $data['code'] : $budget->code,
            ]);
            $this->syncAllocations($budget, $data['allocations'] ?? []);
        });

        return redirect()->route('budgets.show', $budget)->with('success', __('Budget updated.'));
    }

    /**
     * Send a draft or rejected budget for approval by another authorised user.
     */
    public function submit(Request $request, Budget $budget, ApprovalService $approvals): RedirectResponse
    {
        $this->authorizeWorkflow('submit', $budget);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        if (Budget::cents($budget->amount) <= 0) {
            return back()->with('error', __('Enter the approved amount before submitting the budget.'));
        }
        if (Budget::cents($budget->plannedTotal()) > Budget::cents($budget->amount)) {
            return back()->with('error', __('The planned allocations exceed the budget amount. Correct them before submitting.'));
        }

        DB::transaction(function () use ($request, $budget, $approvals, $data) {
            $approvals->request($budget, 'budgets.approve', $request->user(), $data['note'] ?? null);
            $budget->forceFill(['status' => 'submitted'])->save();
            Audit::log('submitted', $budget, $data['note'] ?? null);
        });

        return back()->with('success', __('Budget submitted for approval.'));
    }

    /**
     * Close an approved budget so no further expenses can be recorded.
     */
    public function close(Request $request, Budget $budget): RedirectResponse
    {
        $this->authorizeWorkflow('close', $budget);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        if ($budget->expenses()->where('status', 'pending')->exists()) {
            return back()->with('error', __('This budget still has expenses awaiting approval. Decide or withdraw them before closing the budget.'));
        }

        $budget->forceFill(['status' => 'closed'])->save();
        Audit::log('closed', $budget, $data['note'] ?? null);

        return back()->with('success', __('Budget closed. No further expenses can be recorded against it.'));
    }

    public function destroy(Request $request, Budget $budget, ApprovalService $approvals): RedirectResponse
    {
        $this->authorizeWorkflow('delete', $budget);

        DB::transaction(function () use ($request, $budget, $approvals) {
            if ($pending = $budget->pendingApproval) {
                $approvals->cancel($pending, $request->user());
            }
            $budget->delete();
        });

        return redirect()->route('budgets.index')->with('success', __('Budget deleted.'));
    }

    /**
     * Create, update and remove allocation rows so that each change is
     * recorded individually in the audit trail.
     *
     * @param  array<int|string, array<string, mixed>>  $rows
     */
    private function syncAllocations(Budget $budget, array $rows): void
    {
        $existing = $budget->allocations()->get()->keyBy('id');
        $kept = [];

        foreach ($rows as $row) {
            $attributes = [
                'category_id' => (int) $row['category_id'],
                'planned_amount' => $row['planned_amount'],
                'description' => $row['description'] ?? null,
            ];

            $id = (int) ($row['id'] ?? 0);
            if ($id && $existing->has($id)) {
                $existing[$id]->update($attributes);
                $kept[] = $id;
            } else {
                $kept[] = $budget->allocations()->create($attributes)->id;
            }
        }

        $existing->except($kept)->each->delete();
    }

    /**
     * Totals per currency for the whole filtered set (not just the current page).
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<string, array{amount: float, approved: float, remaining: float}>
     */
    private function totals(array $filters): Collection
    {
        $base = Budget::query()->filter($filters);

        $amounts = (clone $base)->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->pluck('total', 'currency');
        $approved = Expense::query()
            ->join('budgets', 'budgets.id', '=', 'expenses.budget_id')
            ->whereIn('expenses.budget_id', (clone $base)->select('budgets.id'))
            ->where('expenses.status', 'approved')
            ->selectRaw('budgets.currency as currency, SUM(expenses.amount) as total')
            ->groupBy('budgets.currency')
            ->pluck('total', 'currency');

        return $amounts->map(function ($amount, $currency) use ($approved) {
            $amountCents = Budget::cents($amount);
            $approvedCents = Budget::cents($approved[$currency] ?? 0);

            return [
                'amount' => $amountCents / 100,
                'approved' => $approvedCents / 100,
                'remaining' => ($amountCents - $approvedCents) / 100,
            ];
        })->sortKeys();
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'fiscalYears' => Budget::query()->distinct()->orderByDesc('fiscal_year')->pluck('fiscal_year', 'fiscal_year'),
            'projects' => Project::query()->orderBy('title')->get()->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->title]),
            'departments' => Department::options(),
            'fundingSources' => Category::options('funding_source'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Budget $budget): array
    {
        $projects = Project::options();
        if ($budget->project_id && ! isset($projects[$budget->project_id]) && $budget->project) {
            $projects[$budget->project_id] = $budget->project->code.' — '.$budget->project->title;
        }

        $departments = Department::options();
        if ($budget->department_id && ! isset($departments[$budget->department_id]) && $budget->department) {
            $departments[$budget->department_id] = $budget->department->displayName();
        }

        $categoryIds = $budget->exists ? $budget->allocations->pluck('category_id')->filter()->all() : [];
        $categories = Category::options('expense');
        foreach (Category::query()->whereIn('id', $categoryIds)->get() as $category) {
            $categories[$category->id] ??= $category->displayName();
        }

        return [
            'budget' => $budget,
            'canChangeAmounts' => ! $budget->exists || $budget->permits('changeAmounts'),
            'projects' => $projects,
            'departments' => $departments,
            'fundingSources' => Category::options('funding_source', $budget->funding_source_id),
            'categories' => $categories,
        ];
    }
}
