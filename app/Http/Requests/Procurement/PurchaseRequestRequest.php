<?php

namespace App\Http\Requests\Procurement;

use App\Models\PurchaseRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pr = $this->route('purchase_request');

        return $pr instanceof PurchaseRequest
            ? $this->user()->can('update', $pr)
            : $this->user()->can('create', PurchaseRequest::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'],
            'justification' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'budget_id' => ['nullable', 'integer', Rule::exists('budgets', 'id')->where('status', 'approved')->whereNull('deleted_at')],
            'needed_by' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.estimated_unit_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => __('procurement.pr_title'),
            'items' => __('procurement.items'),
            'items.*.description' => __('procurement.item_description'),
            'items.*.quantity' => __('procurement.quantity'),
            'items.*.estimated_unit_cost' => __('procurement.unit_cost'),
            'budget_id' => __('procurement.budget'),
        ];
    }
}
