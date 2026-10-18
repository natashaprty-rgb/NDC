<?php

namespace App\Http\Requests\Assets;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $asset = $this->route('asset');

        return $asset instanceof Asset
            ? $this->user()->can('update', $asset)
            : $this->user()->can('create', Asset::class);
    }

    protected function prepareForValidation(): void
    {
        // Ignore empty specification rows from the repeater.
        $specs = collect($this->input('specifications', []))
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['key'] ?? '')) !== '')
            ->values()->all();
        $this->merge(['specifications' => $specs, 'currency' => strtoupper((string) $this->input('currency', 'USD'))]);
    }

    public function rules(): array
    {
        $asset = $this->route('asset');
        $creating = ! $asset instanceof Asset;

        return [
            'asset_tag' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9][A-Za-z0-9\/_.-]*$/', Rule::unique('assets', 'asset_tag')->ignore($asset?->id)],
            'name' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::exists('asset_categories', 'id')->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $asset?->category_id ?? 0))],
            'description' => ['nullable', 'string', 'max:5000'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120', Rule::unique('assets', 'serial_number')->ignore($asset?->id)],
            'purchase_date' => ['nullable', 'date', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'purchase_order_id' => ['nullable', 'integer', Rule::exists('purchase_orders', 'id')->whereNull('deleted_at')],
            'warranty_expiry' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(Asset::CONDITIONS)],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'assigned_staff_id' => $creating ? ['nullable', 'integer', Rule::exists('staff', 'id')->whereNull('deleted_at')] : ['prohibited'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'specifications' => ['nullable', 'array', 'max:30'],
            'specifications.*.key' => ['required', 'string', 'max:100'],
            'specifications.*.value' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'asset_tag' => __('assets.asset_tag'),
            'name' => __('assets.name'),
            'category_id' => __('assets.category'),
            'serial_number' => __('assets.serial_number'),
            'purchase_cost' => __('assets.purchase_cost'),
            'purchase_date' => __('assets.purchase_date'),
            'condition' => __('assets.condition'),
            'specifications.*.key' => __('assets.spec_key'),
        ];
    }
}
