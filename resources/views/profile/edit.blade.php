<x-layouts.app :title="__('My profile')">
    <x-page-header :title="__('My profile')" />
    <div class="tabs">
        <a href="{{ route('profile.edit') }}" class="is-active">{{ __('Profile') }}</a>
        <a href="{{ route('profile.security') }}">{{ __('Security & sessions') }}</a>
    </div>
    <div class="grid grid-main">
        <div class="card">
            <div class="card__body">
                <form method="POST" action="{{ route('profile.update') }}" class="form">
                    @csrf @method('PUT')
                    <div class="form-grid">
                        <x-form.input name="name" :label="__('Full name')" :value="$user->name" required class="span-2" />
                        <x-form.input name="email" type="email" :label="__('Email address')" :value="$user->email" disabled :help="__('Contact an administrator to change your e-mail address.')" />
                        <x-form.input name="phone" :label="__('Phone')" :value="$user->phone" />
                        <x-form.select name="locale" :label="__('Interface language')" :options="collect(config('ndc.locales'))->map(fn ($l) => $l['native'])" :value="$user->locale" required :placeholder="false" />
                    </div>
                    <div class="form-actions"><button class="btn btn-primary">{{ __('Save changes') }}</button></div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card__head"><h2>{{ __('Account') }}</h2></div>
            <div class="card__body">
                <dl class="dl">
                    <dt>{{ __('Roles') }}</dt><dd>{{ $user->getRoleNames()->map(fn ($r) => __($r))->join(', ') ?: '—' }}</dd>
                    <dt>{{ __('Department') }}</dt><dd>{{ $user->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('Job title') }}</dt><dd>{{ $user->job_title ?? '—' }}</dd>
                    <dt>{{ __('Last sign-in') }}</dt><dd>{{ fmt_date($user->last_login_at, true) }}</dd>
                </dl>
            </div>
        </div>
    </div>
</x-layouts.app>
