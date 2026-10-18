<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $target = $this->route('user');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
            'roles' => ['array'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
        ];

        if (! $target) {
            $rules['password_mode'] = ['required', Rule::in(['generate', 'manual'])];
            $rules['password'] = ['nullable', 'required_if:password_mode,manual', Password::defaults()];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'name' => __('common.name'),
            'email' => __('auth.email'),
            'locale' => __('common.language'),
            'roles' => __('users.roles'),
            'password' => __('users.temporary_password'),
        ];
    }
}
