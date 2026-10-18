<?php

namespace App\Http\Requests\Procurement;

use App\Models\SupplierContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        $contract = $this->route('contract');

        return $contract instanceof SupplierContract
            ? $this->user()->can('update', $contract)
            : $this->user()->can('create', SupplierContract::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'scope' => ['nullable', 'string', 'max:5000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'contract_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'status' => ['required', Rule::in(SupplierContract::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => __('suppliers.contract_title'),
            'end_date' => __('suppliers.end_date'),
            'start_date' => __('suppliers.start_date'),
            'contract_value' => __('suppliers.contract_value'),
        ];
    }
}
