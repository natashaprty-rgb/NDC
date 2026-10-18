<?php

namespace App\Http\Requests\Projects;

use App\Models\ProjectStakeholder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StakeholderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageDetails', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ProjectStakeholder::TYPES)],
            'name' => ['required', 'string', 'max:150'],
            'organization' => ['nullable', 'string', 'max:200'],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'role_description' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function attributes(): array
    {
        return ['type' => __('common.type'), 'name' => __('common.name'), 'supplier_id' => __('projects.supplier')];
    }
}
