<?php

namespace App\Http\Requests\Organisation;

use App\Models\Staff;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff instanceof Staff
            ? $this->user()->can('update', $staff)
            : $this->user()->can('create', Staff::class);
    }

    public function rules(): array
    {
        $staff = $this->route('staff');

        return [
            'staff_number' => ['required', 'string', 'max:30', Rule::unique('staff', 'staff_number')->ignore($staff?->id)],
            'full_name' => ['required', 'string', 'max:150'],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at'), Rule::unique('staff', 'user_id')->ignore($staff?->id)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'job_title' => ['nullable', 'string', 'max:150'],
            'official_email' => ['nullable', 'email', 'max:255'],
            'official_phone' => ['nullable', 'string', 'max:40'],
            'office_location' => ['nullable', 'string', 'max:150'],
            'employment_status' => ['required', Rule::in(Staff::EMPLOYMENT_STATUSES)],
            'employment_type' => ['required', Rule::in(Staff::EMPLOYMENT_TYPES)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'staff_number' => __('staff.staff_number'),
            'full_name' => __('staff.full_name'),
            'user_id' => __('staff.user_account'),
            'department_id' => __('common.department'),
            'job_title' => __('staff.job_title'),
            'official_email' => __('staff.official_email'),
            'employment_status' => __('staff.employment_status'),
            'employment_type' => __('staff.employment_type'),
            'start_date' => __('staff.start_date'),
            'end_date' => __('staff.end_date'),
        ];
    }
}
