<?php

namespace App\Http\Requests\Budgets;

use App\Models\Budget;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a budget and its planned allocations. Once a budget has been
 * submitted or approved only its descriptive fields may change.
 */
class BudgetRequest extends FormRequest
{
    private const MAX_AMOUNT = 9999999999999.99;

    public function authorize(): bool
    {
        // Record-level authorisation is done in the controller via BudgetPolicy.
        return true;
    }

    /**
     * Whether the full set of financial fields is being validated.
     */
    public function changesAmounts(): bool
    {
        $budget = $this->route('budget');

        return ! $budget instanceof Budget
            || ($budget->allows('changeAmounts') && $this->user()->can('changeAmounts', $budget));
    }

    protected function prepareForValidation(): void
    {
        if (! $this->changesAmounts()) {
            return;
        }

        // Ignore completely empty allocation rows added by the repeater.
        $rows = collect($this->input('allocations', []))
            ->filter(fn ($row) => is_array($row) && collect($row)->except('id')->filter(fn ($v) => filled($v))->isNotEmpty())
            ->all();

        $this->merge([
            'allocations' => $rows,
            'currency' => strtoupper(trim((string) $this->input('currency', ''))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $descriptive = [
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if (! $this->changesAmounts()) {
            return $descriptive;
        }

        $budget = $this->route('budget');
        $money = ['required', 'numeric', 'decimal:0,2', 'max:'.self::MAX_AMOUNT];

        return $descriptive + [
            'code' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9\/._-]+$/', Rule::unique('budgets', 'code')->ignore($budget?->id)],
            'fiscal_year' => ['required', 'string', 'max:9', $this->fiscalYearRule()],
            'project_id' => ['nullable', 'integer', 'required_without_all:department_id,funding_source_id', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'funding_source_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('type', 'funding_source')],
            'amount' => [...$money, 'min:0.01'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'allocations' => ['nullable', 'array', 'max:100'],
            'allocations.*.id' => ['nullable', 'integer'],
            'allocations.*.category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('type', 'expense')],
            'allocations.*.planned_amount' => [...$money, 'min:0'],
            'allocations.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->changesAmounts() || $validator->errors()->hasAny(['amount', 'allocations', 'allocations.*'])) {
                    return;
                }

                $planned = collect($this->input('allocations', []))->sum(fn ($row) => Budget::cents($row['planned_amount'] ?? 0));

                if ($planned > Budget::cents($this->input('amount'))) {
                    $validator->errors()->add('allocations', __('The planned allocations total :planned, which exceeds the budget amount of :amount.', [
                        'planned' => money($planned / 100, $this->input('currency')),
                        'amount' => money($this->input('amount'), $this->input('currency')),
                    ]));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'project_id.required_without_all' => __('Select at least one of project, department or funding source.'),
            'code.regex' => __('The code may only contain letters, numbers and the characters / . _ -'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Budget name'),
            'code' => __('Budget code'),
            'fiscal_year' => __('Fiscal year'),
            'project_id' => __('Project'),
            'department_id' => __('Department'),
            'funding_source_id' => __('Funding source'),
            'amount' => __('Approved amount'),
            'currency' => __('Currency'),
            'start_date' => __('Start date'),
            'end_date' => __('End date'),
            'notes' => __('Notes'),
            'allocations.*.category_id' => __('Expense category'),
            'allocations.*.planned_amount' => __('Planned amount'),
            'allocations.*.description' => __('Description'),
        ];
    }

    /**
     * A single year ("2026") or two consecutive years ("2026/2027").
     */
    private function fiscalYearRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || ! preg_match('/^(\d{4})(?:\/(\d{4}))?$/', $value, $m)
                || (isset($m[2]) && (int) $m[2] !== (int) $m[1] + 1)
                || (int) $m[1] < 1990 || (int) $m[1] > 2100) {
                $fail(__('Enter the fiscal year as YYYY or YYYY/YYYY (consecutive years), for example 2026 or 2026/2027.'));
            }
        };
    }
}
