<?php

namespace App\Http\Requests\Meetings;

use App\Models\MeetingItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeetingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordMinutes', $this->route('meeting'));
    }

    public function rules(): array
    {
        return [
            'type' => [$this->isMethod('post') ? 'required' : 'sometimes', Rule::in(MeetingItem::TYPES)],
            'description' => [$this->isMethod('post') ? 'required' : 'sometimes', 'string', 'max:2000'],
            'responsible_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(MeetingItem::STATUSES)],
        ];
    }

    public function attributes(): array
    {
        return [
            'description' => __('meetings.item_description'),
            'responsible_id' => __('meetings.responsible'),
            'due_date' => __('meetings.due_date'),
        ];
    }
}
