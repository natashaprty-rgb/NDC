<?php

namespace App\Http\Requests\Assets;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('move', $this->route('asset'));
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['assigned', 'transferred', 'returned'])],
            'to_staff_id' => ['nullable', 'required_if:action,assigned', 'integer', Rule::exists('staff', 'id')->whereNull('deleted_at')],
            'to_location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
            'to_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'to_staff_id' => __('assets.to_staff'),
            'to_location_id' => __('assets.to_location'),
            'movement_date' => __('assets.movement_date'),
        ];
    }
}
