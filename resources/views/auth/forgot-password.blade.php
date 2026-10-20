<x-layouts.auth :title="__('Reset password')">
    <h1>{{ __('Reset password') }}</h1>
    <p class="muted">{{ __('Enter your account e-mail address and we will send you a reset link.') }}</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <x-form.input name="email" type="email" :label="__('Email address')" required autofocus />
        <button type="submit" class="btn btn-primary" style="width:100%">{{ __('Send reset link') }}</button>
    </form>
    <p class="small" style="margin-top:1rem"><a href="{{ route('login') }}">{{ __('Back to sign in') }}</a></p>
</x-layouts.auth>
