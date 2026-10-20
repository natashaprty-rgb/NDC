<?php

namespace App\Http\Controllers\Budgets;

use App\Http\Controllers\Budgets\Concerns\AuthorizesWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Budgets\ExpenseRequest;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Services\ApprovalService;
use App\Support\FileStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Actual expenditure recorded against approved budgets. Every expense starts
 * as pending and is approved or rejected by another authorised user; only
 * approved expenses count towards utilisation.
 */
class ExpenseController extends Controller
{
    use AuthorizesWorkflow;

    public const FILTERS = ['q', 'budget', 'category', 'status', 'from', 'to', 'project'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $filters = $request->only(self::FILTERS);
        $query = Expense::query()->filter($filters)->with(['budget', 'category', 'recorder']);
        $this->applySort($query, $request, ['expense_date', 'amount', 'status', 'created_at'], 'expense_date');

        return view('expenses.index', [
            'expenses' => $query->paginate($this->perPage($request))->withQueryString(),
            'totals' => self::totals(Expense::query()->filter($filters)),
            ...$this->filterOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Expense::class);

        $budget = Budget::query()->find($request->integer('budget'));
        if ($budget) {
            $this->authorizeWorkflow('recordExpense', $budget);
        }

        return view('expenses.form', $this->formData(new Expense([
            'expense_date' => now()->toDateString(),
        ]), $budget));
    }

    public function store(ExpenseRequest $request, ApprovalService $approvals): RedirectResponse
    {
        $this->authorize('create', Expense::class);
        $budget = Budget::query()->findOrFail($request->integer('budget_id'));
        $this->authorizeWorkflow('recordExpense', $budget);

        $data = $request->validated();

        $expense = DB::transaction(function () use ($budget, $data, $request, $approvals) {
            /** @var Expense $expense */
            $expense = $budget->expenses()->create([
                ...collect($data)->except(['budget_id', 'receipts'])->all(),
                'status' => 'pending',
                'recorded_by' => $request->user()->id,
            ]);
            $approvals->request($expense, 'expenses.approve', $request->user());

            return $expense;
        });

        $this->storeReceipts($request, $expense);

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('Expense recorded and sent for approval.'))
            ->with('warning', $this->overBudgetWarning($budget));
    }

    public function show(Expense $expense): View
    {
        $this->authorize('view', $expense);

        $expense->load(['budget', 'category', 'purchaseOrder', 'recorder', 'attachments.uploader']);

        return view('expenses.show', [
            'expense' => $expense,
            'summary' => $expense->budget?->spendSummary(),
        ]);
    }

    public function edit(Expense $expense): View
    {
        $this->authorizeWorkflow('update', $expense);

        return view('expenses.form', $this->formData($expense, $expense->budget));
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorizeWorkflow('update', $expense);

        $expense->update(collect($request->validated())->except(['budget_id', 'receipts'])->all());
        $this->storeReceipts($request, $expense);

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('Expense updated.'))
            ->with('warning', $this->overBudgetWarning($expense->budget));
    }

    /**
     * Withdraw a pending expense: cancel its approval request and archive it.
     */
    public function destroy(Request $request, Expense $expense, ApprovalService $approvals): RedirectResponse
    {
        $this->authorizeWorkflow('delete', $expense);

        DB::transaction(function () use ($request, $expense, $approvals) {
            if ($pending = $expense->pendingApproval) {
                $approvals->cancel($pending, $request->user());
            }
            $expense->delete();
        });

        return redirect()->route('budgets.show', $expense->budget_id)->with('success', __('Expense withdrawn.'));
    }

    /**
     * Totals per currency and status for a filtered expense query.
     *
     * @return Collection<string, array{total: float, approved: float, pending: float, rejected: float}>
     */
    public static function totals(Builder $query): Collection
    {
        $rows = $query->reorder()
            ->join('budgets', 'budgets.id', '=', 'expenses.budget_id')
            ->selectRaw('budgets.currency as currency, expenses.status as status, SUM(expenses.amount) as total')
            ->groupBy('budgets.currency', 'expenses.status')
            ->get();

        return $rows->groupBy('currency')->map(function (Collection $byStatus) {
            $cents = fn (string $status) => Budget::cents($byStatus->firstWhere('status', $status)?->total ?? 0);
            $all = $byStatus->sum(fn ($row) => Budget::cents($row->total));

            return [
                'total' => $all / 100,
                'approved' => $cents('approved') / 100,
                'pending' => $cents('pending') / 100,
                'rejected' => $cents('rejected') / 100,
            ];
        })->sortKeys();
    }

    private function storeReceipts(ExpenseRequest $request, Expense $expense): void
    {
        foreach ($request->file('receipts', []) as $file) {
            FileStore::attach($expense, $file, 'receipts');
        }
    }

    /**
     * Warn (without blocking) when approved and pending spend together exceed
     * the budget amount.
     */
    private function overBudgetWarning(?Budget $budget): ?string
    {
        if ($budget === null) {
            return null;
        }

        $summary = $budget->spendSummary();

        return $summary['over_committed']
            ? __('Warning: approved and pending expenses (:committed) now exceed the budget amount of :amount.', [
                'committed' => money($summary['committed'], $budget->currency),
                'amount' => money($summary['amount'], $budget->currency),
            ])
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'budgets' => Budget::query()->orderBy('code')->get()->mapWithKeys(fn (Budget $b) => [$b->id => $b->code.' — '.$b->name]),
            'categories' => Category::options('expense'),
            'projects' => Project::query()->orderBy('title')->get()->mapWithKeys(fn (Project $p) => [$p->id => $p->code.' — '.$p->title]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Expense $expense, ?Budget $budget): array
    {
        $purchaseOrders = PurchaseOrder::query()
            ->where(fn (Builder $q) => $q->where('status', '!=', 'cancelled')
                ->when($expense->purchase_order_id, fn (Builder $q, $id) => $q->orWhere('id', $id)))
            ->with('supplier')->latest('order_date')->limit(300)->get()
            ->mapWithKeys(fn (PurchaseOrder $po) => [$po->id => $po->po_number.($po->supplier ? ' — '.$po->supplier->name : '')]);

        return [
            'expense' => $expense,
            'budget' => $budget,
            'budgets' => Budget::options(),
            'categories' => Category::options('expense', $expense->category_id),
            'purchaseOrders' => $purchaseOrders,
        ];
    }
}
