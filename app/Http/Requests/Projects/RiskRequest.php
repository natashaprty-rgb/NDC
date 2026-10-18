<?php

namespace App\Http\Requests\Projects;

use App\Models\ProjectRisk;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RiskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageDetails', $this->route('project'));
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(ProjectRisk::TYPES)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'likelihood' => ['required', Rule::in(ProjectRisk::LEVELS)],
            'impact' => ['required', Rule::in(ProjectRisk::LEVELS)],
            'status' => ['required', Rule::in(ProjectRisk::STATUSES)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'corrective_action' => ['nullable', 'string', 'max:2000'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => __('common.title'), 'likelihood' => __('projects.likelihood'), 'impact' => __('projects.impact'),
            'owner_id' => __('projects.risk_owner'), 'status' => __('common.status'),
        ];
    }
}
