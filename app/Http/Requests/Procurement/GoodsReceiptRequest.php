<?php

namespace App\Http\Requests\Procurement;

use App\Services\FileStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('receive', $this->route('purchase_order'));
    }

    public function rules(): array
    {
        $po = $this->route('purchase_order');

        return [
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'delivery_note_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', Rule::exists('purchase_order_items', 'id')->where('purchase_order_id', $po->id)],
            'items.*.quantity_received' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'items.*.condition_note' => ['nullable', 'string', 'max:300'],
            'file' => FileStorage::rules(false),
        ];
    }

    public function attributes(): array
    {
        return [
            'received_date' => __('procurement.received_date'),
            'items.*.quantity_received' => __('procurement.quantity_received'),
        ];
    }
}
