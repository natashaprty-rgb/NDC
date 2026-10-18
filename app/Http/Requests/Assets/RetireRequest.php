<?php

namespace App\Http\Requests\Assets;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RetireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('retire', $this->route('asset'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(Asset::RETIRED_STATUSES)],
            'retired_on' => ['required', 'date', 'before_or_equal:today'],
            'retirement_reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['retired_on' => __('assets.retired_on'), 'retirement_reason' => __('assets.retirement_reason')];
    }
}
