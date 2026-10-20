<?php

namespace App\Http\Requests\Budgets;

use App\Models\Budget;
use App\Models\Expense;
use App\Support\FileStore;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an expense recorded against an approved budget, including the
 * optional receipts/invoices uploaded with it.
 */
class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Record-level authorisation is done in the controller via the policies.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('type', 'expense')],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'payee' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'expense_date' => ['required', 'date'],
            'receipts' => ['nullable', 'array', 'max:10'],
            'receipts.*' => FileStore::rules(),
        ];

        if (! $this->route('expense') instanceof Expense) {
            $rules['budget_id'] = ['required', 'integer', Rule::exists('budgets', 'id')
                ->where('status', 'approved')->whereNull('deleted_at')];
        }

        return $rules;
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $budget = $this->budget();
                if ($budget === null || $validator->errors()->has('expense_date')) {
                    return;
                }

                $date = $this->date('expense_date');
                if ($budget->start_date && $date->lt($budget->start_date)
                    || $budget->end_date && $date->gt($budget->end_date)) {
                    $validator->errors()->add('expense_date', __('The expense date must fall within the budget period (:start – :end).', [
                        'start' => fmt_date($budget->start_date),
                        'end' => fmt_date($budget->end_date),
                    ]));
                }
            },
        ];
    }

    /**
     * The budget the expense is (or will be) recorded against.
     */
    public function budget(): ?Budget
    {
        $expense = $this->route('expense');
        if ($expense instanceof Expense) {
            return $expense->budget;
        }

        $id = $this->input('budget_id');

        return is_numeric($id) ? Budget::query()->find((int) $id) : null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'budget_id.exists' => __('Expenses can only be recorded against an approved budget that has not been closed.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'budget_id' => __('Budget'),
            'category_id' => __('Expense category'),
            'purchase_order_id' => __('Purchase order'),
            'reference' => __('Reference'),
            'description' => __('Description'),
            'payee' => __('Payee'),
            'amount' => __('Amount'),
            'expense_date' => __('Expense date'),
            'receipts' => __('Receipts and invoices'),
            'receipts.*' => __('Receipt file'),
        ];
    }
}
