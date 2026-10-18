<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PermissionReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('permissions.review');
    }

    public function rules(): array
    {
        return [
            'review_date' => ['required', 'date', 'before_or_equal:today'],
            'findings' => ['required', 'string', 'max:5000'],
            'actions_taken' => ['nullable', 'string', 'max:5000'],
            'next_review_due' => ['required', 'date', 'after:review_date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'review_date' => __('audit.review_date'),
            'findings' => __('audit.findings'),
            'actions_taken' => __('audit.actions_taken'),
            'next_review_due' => __('audit.next_review_due'),
        ];
    }
}
