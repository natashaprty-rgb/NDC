<x-guest-layout :title="__('auth.reset_title')">
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-field.input name="email" type="email" :label="__('auth.email')" :value="$email" :required="true" />
        <x-field.input name="password" type="password" :label="__('auth.new_password')" :required="true" :help="__('auth.password_rules')" autocomplete="new-password" />
        <x-field.input name="password_confirmation" type="password" :label="__('auth.confirm_password')" :required="true" autocomplete="new-password" />
        <div class="auth-footer">
            <a href="{{ route('login') }}">{{ __('auth.back_to_login') }}</a>
            <button type="submit" class="btn btn--primary">{{ __('auth.reset_password') }}</button>
        </div>
    </form>
</x-guest-layout>
