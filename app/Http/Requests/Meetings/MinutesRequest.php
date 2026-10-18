<?php

namespace App\Http\Requests\Meetings;

use App\Models\MeetingParticipant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MinutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('recordMinutes', $this->route('meeting'));
    }

    public function rules(): array
    {
        return [
            'minutes' => ['nullable', 'string', 'max:100000'],
            'attendance' => ['nullable', 'array'],
            'attendance.*' => [Rule::in(MeetingParticipant::ATTENDANCE)],
            'mark_completed' => ['boolean'],
        ];
    }
}
