<?php

namespace App\Http\Requests\Assets;

use App\Models\AssetMaintenance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('maintain', $this->route('asset'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(AssetMaintenance::TYPES)],
            'description' => ['required', 'string', 'max:2000'],
            'provider' => ['nullable', 'string', 'max:200'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'scheduled_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date', 'before_or_equal:today'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'status' => ['required', Rule::in(AssetMaintenance::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['description' => __('common.description'), 'cost' => __('assets.cost'), 'completed_date' => __('assets.completed_date')];
    }
}
