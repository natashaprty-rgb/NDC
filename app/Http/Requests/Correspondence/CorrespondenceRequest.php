<?php

namespace App\Http\Requests\Correspondence;

use App\Models\Correspondence;
use App\Services\FileStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CorrespondenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('correspondence');

        return $item instanceof Correspondence
            ? $this->user()->can('update', $item)
            : $this->user()->can('create', Correspondence::class);
    }

    public function rules(): array
    {
        $item = $this->route('correspondence');
        $creating = ! ($item instanceof Correspondence);

        $rules = [
            'external_reference' => ['nullable', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:300'],
            'sender' => ['required', 'string', 'max:200'],
            'recipient' => ['required', 'string', 'max:200'],
            'letter_date' => ['nullable', 'date'],
            'received_or_sent_date' => ['required', 'date'],
            'delivery_method' => ['nullable', Rule::in(Correspondence::DELIVERY_METHODS)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'response_deadline' => ['nullable', 'date', 'after_or_equal:received_or_sent_date'],
            'priority' => ['required', Rule::in(Correspondence::PRIORITIES)],
            'confidentiality' => ['required', Rule::in(Correspondence::CONFIDENTIALITY)],
            'summary' => ['nullable', 'string', 'max:5000'],
            'in_reply_to_id' => ['nullable', 'integer', Rule::exists('correspondences', 'id')->whereNull('deleted_at'), Rule::notIn(array_filter([$item?->id]))],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
        ];

        if ($creating) {
            $rules['direction'] = ['required', Rule::in(Correspondence::DIRECTIONS)];
            $rules['file'] = FileStorage::rules(false);
        }

        return $rules;
    }

    /** The linked letter must itself be visible to the user (no IDOR via reply links). */
    public function after(): array
    {
        return [function (Validator $validator) {
            $id = $this->input('in_reply_to_id');
            if ($id && ! $validator->errors()->has('in_reply_to_id')) {
                $parent = Correspondence::query()->find($id);
                if (! $parent || ! $parent->canBeViewedBy($this->user())) {
                    $validator->errors()->add('in_reply_to_id', __('validation.exists', ['attribute' => __('correspondence.in_reply_to')]));
                }
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'direction' => __('correspondence.direction'),
            'external_reference' => __('correspondence.external_reference'),
            'subject' => __('correspondence.subject'),
            'sender' => __('correspondence.sender'),
            'recipient' => __('correspondence.recipient'),
            'letter_date' => __('correspondence.letter_date'),
            'received_or_sent_date' => __('correspondence.received_or_sent_date'),
            'delivery_method' => __('correspondence.delivery_method'),
            'department_id' => __('common.department'),
            'assigned_to' => __('correspondence.assigned_officer'),
            'response_deadline' => __('correspondence.response_deadline'),
            'priority' => __('correspondence.priority'),
            'confidentiality' => __('correspondence.confidentiality'),
            'in_reply_to_id' => __('correspondence.in_reply_to'),
            'file' => __('correspondence.scanned_letter'),
        ];
    }
}
