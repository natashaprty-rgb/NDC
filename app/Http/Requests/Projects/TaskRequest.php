<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof Task
            ? $this->user()->can('update', $task)
            : $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        $activeUser = Rule::exists('users', 'id')->where('status', 'active')->whereNull('deleted_at');

        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'deliverables' => ['nullable', 'string', 'max:2000'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')->whereNull('archived_at')],
            'milestone_id' => ['nullable', 'integer', Rule::exists('project_milestones', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'priority' => ['required', Rule::in(Task::PRIORITIES)],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'assigned_to' => ['nullable', 'integer', $activeUser],
            'requires_approval' => ['boolean'],
            'approver_id' => ['nullable', 'integer', $activeUser, 'different:assigned_to'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $projectId = $this->input('project_id');
                $milestoneId = $this->input('milestone_id');
                if ($milestoneId) {
                    $milestone = ProjectMilestone::query()->find($milestoneId);
                    if (! $milestone || ! $projectId || (int) $milestone->project_id !== (int) $projectId) {
                        $validator->errors()->add('milestone_id', __('tasks.milestone_mismatch'));
                    }
                }
                if ($projectId && ($project = Project::query()->find($projectId)) && ! $this->user()->can('view', $project)) {
                    $validator->errors()->add('project_id', __('tasks.project_not_allowed'));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => __('common.title'), 'project_id' => __('common.project'), 'milestone_id' => __('tasks.milestone'),
            'department_id' => __('common.department'), 'priority' => __('tasks.priority'), 'start_date' => __('tasks.start_date'),
            'due_date' => __('tasks.due_date'), 'assigned_to' => __('common.assigned_to'), 'approver_id' => __('tasks.approver'),
        ];
    }
}
