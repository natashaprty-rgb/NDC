<?php

namespace App\Http\Requests\Procurement;

use App\Services\FileStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('order', $this->route('purchase_request'));
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'quotation_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:quotation_date'],
            'total_amount' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => FileStorage::rules(false),
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => __('procurement.supplier'),
            'total_amount' => __('procurement.quoted_total'),
        ];
    }
}
