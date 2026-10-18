<x-guest-layout :title="__('auth.mfa_title')">
    <p class="muted small">{{ __('auth.mfa_help') }}</p>
    <form method="POST" action="{{ route('mfa.verify') }}">
        @csrf
        <x-field.input name="code" :label="__('auth.mfa_code')" :required="true" autocomplete="one-time-code" inputmode="text" autofocus />
        <div class="auth-footer">
            <a href="{{ route('login') }}">{{ __('auth.back_to_login') }}</a>
            <button type="submit" class="btn btn--primary">{{ __('auth.verify') }}</button>
        </div>
    </form>
</x-guest-layout>
