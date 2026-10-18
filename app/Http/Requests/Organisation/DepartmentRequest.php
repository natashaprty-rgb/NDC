<?php

namespace App\Http\Requests\Organisation;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            ? $this->user()->can('update', $department)
            : $this->user()->can('create', Department::class);
    }

    public function rules(): array
    {
        $department = $this->route('department');

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('departments', 'code')->ignore($department?->id)],
            'name' => ['required', 'string', 'max:150'],
            'name_so' => ['nullable', 'string', 'max:150'],
            'parent_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at'), Rule::notIn(array_filter([$department?->id]))],
            'head_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => __('departments.code'),
            'name' => __('departments.name'),
            'name_so' => __('departments.name_so'),
            'parent_id' => __('departments.parent'),
            'head_user_id' => __('departments.head'),
        ];
    }
}
