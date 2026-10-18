<?php

namespace App\Http\Requests\Documents;

use App\Models\Document;
use App\Services\FileStorage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document
            ? $this->user()->can('update', $document)
            : $this->user()->can('create', Document::class);
    }

    public function rules(): array
    {
        $creating = ! ($this->route('document') instanceof Document);

        $rules = [
            'title' => ['required', 'string', 'max:250'],
            'category_id' => ['nullable', 'integer', Rule::exists('document_categories', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->whereNull('deleted_at')],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')->where('status', 'active')],
            'registration_date' => ['required', 'date'],
            'confidentiality' => ['required', Rule::in(Document::CONFIDENTIALITY)],
            'review_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:registration_date'],
        ];

        if ($creating) {
            $rules['file'] = FileStorage::rules(true);
            $rules['change_note'] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'title' => __('documents.doc_title'),
            'category_id' => __('common.category'),
            'department_id' => __('common.department'),
            'project_id' => __('common.project'),
            'owner_id' => __('common.owner'),
            'registration_date' => __('documents.registration_date'),
            'confidentiality' => __('documents.confidentiality'),
            'review_date' => __('documents.review_date'),
            'expiry_date' => __('documents.expiry_date'),
            'file' => __('common.file'),
        ];
    }
}
