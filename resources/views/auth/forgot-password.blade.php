<x-guest-layout :title="__('auth.forgot_title')">
    <p class="muted small">{{ __('auth.forgot_help') }}</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-field.input name="email" type="email" :label="__('auth.email')" :required="true" autofocus />
        <div class="auth-footer">
            <a href="{{ route('login') }}">{{ __('auth.back_to_login') }}</a>
            <button type="submit" class="btn btn--primary">{{ __('auth.send_link') }}</button>
        </div>
    </form>
</x-guest-layout>
