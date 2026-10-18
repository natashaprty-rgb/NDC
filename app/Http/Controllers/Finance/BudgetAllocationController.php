<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\BudgetAllocationRequest;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use Illuminate\Http\RedirectResponse;

/** Allocations can only change while the budget is draft or submitted (BudgetPolicy::update). */
class BudgetAllocationController extends Controller
{
    public function store(BudgetAllocationRequest $request, Budget $budget): RedirectResponse
    {
        $budget->allocations()->create($request->validated());

        return redirect()->route('budgets.show', $budget)->with('success', __('budgets.allocation_saved'));
    }

    public function update(BudgetAllocationRequest $request, Budget $budget, BudgetAllocation $allocation): RedirectResponse
    {
        $allocation->update($request->safe()->only(['amount', 'description']));

        return redirect()->route('budgets.show', $budget)->with('success', __('budgets.allocation_saved'));
    }

    public function destroy(Budget $budget, BudgetAllocation $allocation): RedirectResponse
    {
        $this->authorize('update', $budget);

        if ($budget->expenses()->where('expense_category_id', $allocation->expense_category_id)->exists()) {
            return back()->with('error', __('budgets.allocation_has_expenses'));
        }
        $allocation->delete();

        return redirect()->route('budgets.show', $budget)->with('success', __('common.deleted'));
    }
}
