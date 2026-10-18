<?php

namespace App\Http\Requests\Procurement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('order', $this->route('purchase_request'));
    }

    public function rules(): array
    {
        $pr = $this->route('purchase_request');

        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('status', 'active')->whereNull('deleted_at')],
            'quotation_id' => ['nullable', 'integer', Rule::exists('quotations', 'id')->where('purchase_request_id', $pr->id)],
            'order_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.purchase_request_item_id' => ['required', 'integer', Rule::exists('purchase_request_items', 'id')->where('purchase_request_id', $pr->id)],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => __('procurement.supplier'),
            'order_date' => __('procurement.order_date'),
            'items.*.quantity' => __('procurement.quantity'),
            'items.*.unit_price' => __('procurement.unit_price'),
        ];
    }
}
