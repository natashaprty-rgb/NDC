<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\ExpenseRequest;
use App\Models\Budget;
use App\Models\Department;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\FileStorage;
use App\Services\Finance\ExpenseWorkflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseWorkflow $workflow) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $query = $this->filtered($request);
        $totals = (clone $query)->reorder()->selectRaw('currency, COALESCE(SUM(amount), 0) as total, COUNT(*) as n')
            ->groupBy('currency')->orderBy('currency')->get();
        $expenses = $query->with(['budget', 'category', 'recorder'])
            ->orderByDesc('expense_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'totals' => $totals,
            'budgets' => Budget::query()->orderByDesc('fiscal_year')->get()->mapWithKeys(fn ($b) => [$b->id => $b->reference.' — '.$b->title])->all(),
            'categories' => ExpenseCategory::query()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all(),
            'projects' => Project::query()->orderBy('name')->pluck('name', 'id')->all(),
            'departments' => Department::query()->orderBy('name')->get()->mapWithKeys(fn ($d) => [$d->id => $d->displayName()])->all(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Expense::class);

        $expense = new Expense([
            'budget_id' => $request->integer('budget_id') ?: null,
            'expense_date' => today(),
        ]);

        return view('expenses.form', ['expense' => $expense] + $this->formOptions());
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        $data = $request->safe()->except('receipt');
        $expense = $this->workflow->record($data, $request->user());

        if ($request->hasFile('receipt')) {
            FileStorage::attach($expense, $request->file('receipt'), 'receipt', 'receipt');
        }

        return redirect()->route('expenses.show', $expense)->with('success', __('expenses.recorded'));
    }

    public function show(Expense $expense): View
    {
        $this->authorize('view', $expense);

        $expense->load(['budget', 'category', 'supplier', 'purchaseOrder', 'recorder', 'approvedBy', 'rejectedBy', 'settledBy']);

        return view('expenses.show', compact('expense'));
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        return view('expenses.form', ['expense' => $expense] + $this->formOptions($expense));
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $data = $request->safe()->except('receipt');
        $data['currency'] = Budget::query()->findOrFail($data['budget_id'])->currency;
        $expense->update($data);

        if ($request->hasFile('receipt')) {
            FileStorage::attach($expense, $request->file('receipt'), 'receipt', 'receipt');
        }

        return redirect()->route('expenses.show', $expense)->with('success', __('common.updated'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', __('common.deleted'));
    }

    public function approve(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);
        $this->workflow->approve($expense, $request->user());

        return back()->with('success', __('expenses.approved'));
    }

    public function reject(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('approve', $expense);
        $data = $request->validate(['review_note' => ['required', 'string', 'max:2000']], [], ['review_note' => __('common.reason')]);
        $this->workflow->reject($expense, $request->user(), $data['review_note']);

        return back()->with('success', __('expenses.rejected'));
    }

    public function markPaid(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('markPaid', $expense);
        $data = $request->validate([
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ], [], ['paid_on' => __('expenses.paid_on'), 'payment_reference' => __('expenses.payment_reference')]);
        $this->workflow->markPaid($expense, $request->user(), $data['paid_on'], $data['payment_reference'] ?? null);

        return back()->with('success', __('expenses.paid_recorded'));
    }

    public function reconcile(Request $request, Expense $expense): RedirectResponse
    {
        $this->authorize('reconcile', $expense);
        $this->workflow->reconcile($expense, $request->user());

        return back()->with('success', __('expenses.reconciled_recorded'));
    }

    private function filtered(Request $request): Builder
    {
        return Expense::query()
            ->when($request->filled('budget_id'), fn ($q) => $q->where('budget_id', $request->integer('budget_id')))
            ->when($request->filled('expense_category_id'), fn ($q) => $q->where('expense_category_id', $request->integer('expense_category_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', (string) $request->input('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('expense_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('expense_date', '<=', $request->date('to')))
            ->when($request->filled('project_id'), fn ($q) => $q->whereHas('budget', fn ($b) => $b->where('project_id', $request->integer('project_id'))))
            ->when($request->filled('department_id'), fn ($q) => $q->whereHas('budget', fn ($b) => $b->where('department_id', $request->integer('department_id'))))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('reference', 'like', $term)->orWhere('description', 'like', $term)
                    ->orWhere('payee', 'like', $term)->orWhere('invoice_number', 'like', $term));
            });
    }

    private function formOptions(?Expense $current = null): array
    {
        $budgets = Budget::query()->where('status', 'approved')
            ->when($current, fn ($q) => $q->orWhere('id', $current->budget_id))
            ->orderByDesc('fiscal_year')->orderBy('title')->get();

        return [
            'budgets' => $budgets->mapWithKeys(fn ($b) => [$b->id => $b->reference.' — '.$b->title.' ('.$b->currency.')'])->all(),
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->displayName()])->all(),
            'suppliers' => Supplier::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id')->all(),
            'purchaseOrders' => PurchaseOrder::query()->where('status', '!=', 'cancelled')->orderByDesc('id')->limit(500)->pluck('reference', 'id')->all(),
        ];
    }
}
