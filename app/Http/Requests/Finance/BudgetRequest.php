<?php

namespace App\Http\Requests\Finance;

use App\Models\Budget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof Budget
            ? $this->user()->can('update', $budget)
            : $this->user()->can('create', Budget::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper(trim((string) $this->input('currency')))]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after:period_start'],
            'project_id' => ['nullable', 'required_without:department_id', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'required_without:project_id', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'funding_source_id' => ['nullable', 'integer', Rule::exists('funding_sources', 'id')->where('is_active', true)],
            'currency' => ['required', 'string', 'regex:/^[A-Z]{3}$/'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => __('budgets.title_field'),
            'fiscal_year' => __('budgets.fiscal_year'),
            'period_start' => __('budgets.period_start'),
            'period_end' => __('budgets.period_end'),
            'project_id' => __('common.project'),
            'department_id' => __('common.department'),
            'funding_source_id' => __('budgets.funding_source'),
            'currency' => __('budgets.currency'),
        ];
    }
}
