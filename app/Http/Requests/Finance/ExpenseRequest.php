<?php

namespace App\Http\Requests\Finance;

use App\Models\Budget;
use App\Models\Expense;
use App\Services\FileStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            ? $this->user()->can('update', $expense)
            : $this->user()->can('create', Expense::class);
    }

    public function rules(): array
    {
        return [
            'budget_id' => ['required', 'integer', Rule::exists('budgets', 'id')->whereNull('deleted_at')],
            'expense_category_id' => ['required', 'integer', Rule::exists('expense_categories', 'id')],
            'description' => ['required', 'string', 'max:500'],
            'payee' => ['nullable', 'string', 'max:200'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99', 'decimal:0,2'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'receipt' => FileStorage::rules(false),
        ];
    }

    /** Business rules: approved budget, allocated category, date within the budget period. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $budget = Budget::query()->find($this->integer('budget_id'));
            if (! $budget || $budget->status !== 'approved') {
                $validator->errors()->add('budget_id', __('expenses.budget_not_approved'));

                return;
            }
            if (! $budget->allocations()->where('expense_category_id', $this->integer('expense_category_id'))->exists()) {
                $validator->errors()->add('expense_category_id', __('expenses.category_not_allocated'));
            }
            $date = Carbon::parse($this->input('expense_date'));
            if (($budget->period_start && $date->lt($budget->period_start)) || ($budget->period_end && $date->gt($budget->period_end))) {
                $validator->errors()->add('expense_date', __('expenses.date_outside_period', [
                    'start' => fmt_date($budget->period_start), 'end' => fmt_date($budget->period_end),
                ]));
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'budget_id' => __('expenses.budget'),
            'expense_category_id' => __('expenses.category'),
            'description' => __('common.description'),
            'payee' => __('expenses.payee'),
            'invoice_number' => __('expenses.invoice_number'),
            'amount' => __('expenses.amount'),
            'expense_date' => __('expenses.expense_date'),
            'supplier_id' => __('expenses.supplier'),
            'purchase_order_id' => __('expenses.purchase_order'),
            'receipt' => __('expenses.receipt'),
        ];
    }
}
