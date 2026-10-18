<x-guest-layout :title="__('auth.sign_in_title')">
    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf
        <x-field.input name="email" type="email" :label="__('auth.email')" :required="true" autocomplete="username" autofocus />
        <x-field.input name="password" type="password" :label="__('auth.password_label')" :required="true" autocomplete="current-password" />
        <label class="checkbox"><input type="checkbox" name="remember" value="1"> {{ __('auth.remember') }}</label>
        <div class="auth-footer">
            <a href="{{ route('password.request') }}">{{ __('auth.forgot') }}</a>
            <button type="submit" class="btn btn--primary">{{ __('auth.sign_in') }}</button>
        </div>
    </form>
</x-guest-layout>
