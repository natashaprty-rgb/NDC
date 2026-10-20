<x-layouts.auth :title="__('Two-factor verification')">
    <h1>{{ __('Two-factor verification') }}</h1>
    <p class="muted">{{ __('Enter the 6-digit code from your authenticator app, or one of your recovery codes.') }}</p>
    <form method="POST" action="{{ route('two-factor.verify') }}">
        @csrf
        <x-form.input name="code" :label="__('Verification code')" required autofocus autocomplete="one-time-code" inputmode="text" maxlength="20" />
        <button type="submit" class="btn btn-primary" style="width:100%">{{ __('Verify') }}</button>
    </form>
    <p class="small" style="margin-top:1rem"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
</x-layouts.auth>
