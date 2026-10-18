<?php

namespace App\Http\Requests\Finance;

use App\Models\BudgetAllocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('budget'));
    }

    public function rules(): array
    {
        $budget = $this->route('budget');
        $allocation = $this->route('allocation');

        return [
            'expense_category_id' => [
                $allocation instanceof BudgetAllocation ? 'prohibited' : 'required',
                'integer',
                Rule::exists('expense_categories', 'id')->where('is_active', true),
                Rule::unique('budget_allocations', 'expense_category_id')->where('budget_id', $budget->id),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'description' => ['nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return ['expense_category_id.unique' => __('budgets.category_already_allocated')];
    }

    public function attributes(): array
    {
        return [
            'expense_category_id' => __('budgets.category'),
            'amount' => __('budgets.amount'),
            'description' => __('common.description'),
        ];
    }
}
