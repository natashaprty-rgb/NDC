<x-layouts.auth :title="__('Sign in')">
    <h1>{{ __('Sign in') }}</h1>
    <p class="muted">{{ __('Use your official NDC account.') }}</p>
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <x-form.input name="email" type="email" :label="__('Email address')" required autofocus autocomplete="username" />
        <x-form.input name="password" type="password" :label="__('Password')" required autocomplete="current-password" />
        <div class="form-actions" style="justify-content:space-between">
            <x-form.checkbox name="remember" :label="__('Keep me signed in')" class="mb-0" />
            <a href="{{ route('password.request') }}" class="small">{{ __('Forgot your password?') }}</a>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem">{{ __('Sign in') }}</button>
    </form>
    <p class="small muted" style="margin-top:1.5rem">{{ __('Access is restricted to authorised personnel. Activity is logged.') }}</p>
</x-layouts.auth>
