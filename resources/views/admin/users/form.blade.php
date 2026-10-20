<x-layouts.app :title="$user->exists ? __('Edit user') : __('New user')">
    <x-page-header :title="$user->exists ? __('Edit user') : __('New user')" :breadcrumbs="[__('Users') => route('admin.users.index')]" />
    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="card form">
        @csrf
        @if ($user->exists) @method('PUT') @endif
        <div class="card__body">
            <div class="form-grid">
                <x-form.input name="name" :label="__('Full name')" :value="$user->name" required />
                <x-form.input name="email" type="email" :label="__('Email address')" :value="$user->email" required />
                <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$user->department_id" />
                <x-form.input name="job_title" :label="__('Job title')" :value="$user->job_title" />
                <x-form.input name="phone" :label="__('Phone')" :value="$user->phone" />
                <x-form.select name="status" :label="__('Status')" :options="\App\Support\Status::options('user')" :value="$user->status" required :placeholder="false" />
                <x-form.select name="locale" :label="__('Interface language')" :options="collect(config('ndc.locales'))->map(fn ($l) => $l['native'])" :value="$user->locale" required :placeholder="false" />
            </div>
            <fieldset>
                <legend>{{ __('Roles') }}</legend>
                <div class="checks">
                    @foreach ($roles as $role)
                        <label class="check"><input type="checkbox" name="roles[]" value="{{ $role }}" @checked(in_array($role, old('roles', $user->exists ? $user->getRoleNames()->all() : []), true))> {{ __($role) }}</label>
                    @endforeach
                </div>
                @error('roles')<div class="field-error">{{ $message }}</div>@enderror
            </fieldset>
            <fieldset>
                <legend>{{ $user->exists ? __('Set a new password (optional)') : __('Initial password') }}</legend>
                <div class="form-grid">
                    <x-form.input name="password" type="password" :label="__('Password')" :required="! $user->exists" autocomplete="new-password"
                        :help="__('The user will be asked to change it at first sign-in.')" />
                    <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" :required="! $user->exists" autocomplete="new-password" />
                </div>
                @unless ($user->exists)
                    <x-form.checkbox name="send_reset" :label="__('Also e-mail a password reset link to the user')" />
                @endunless
            </fieldset>
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $user->exists ? route('admin.users.show', $user) : route('admin.users.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
