<?php

namespace App\Http\Requests\Meetings;

use App\Models\Meeting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $meeting = $this->route('meeting');

        return $meeting instanceof Meeting
            ? $this->user()->can('update', $meeting)
            : $this->user()->can('create', Meeting::class);
    }

    protected function prepareForValidation(): void
    {
        // Drop blank repeater rows for external participants.
        $externals = collect($this->input('externals', []))
            ->filter(fn ($row) => is_array($row) && trim((string) ($row['name'] ?? '')) !== '')
            ->values()->all();
        $this->merge(['externals' => $externals]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:250'],
            'meeting_type' => ['required', Rule::in(Meeting::TYPES)],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:250'],
            'online_link' => ['nullable', 'url:http,https', 'max:500'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'status' => ['required', Rule::in(Meeting::STATUSES)],
            'organizer_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'agenda' => ['nullable', 'string', 'max:20000'],
            'participant_ids' => ['nullable', 'array', 'max:200'],
            'participant_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'externals' => ['nullable', 'array', 'max:50'],
            'externals.*.name' => ['required', 'string', 'max:150'],
            'externals.*.organization' => ['nullable', 'string', 'max:200'],
            'externals.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => __('meetings.meeting_title'),
            'meeting_type' => __('meetings.type'),
            'starts_at' => __('meetings.starts_at'),
            'ends_at' => __('meetings.ends_at'),
            'online_link' => __('meetings.online_link'),
            'externals.*.name' => __('meetings.external_name'),
            'externals.*.email' => __('meetings.external_email'),
        ];
    }
}
