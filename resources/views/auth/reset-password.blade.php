<x-layouts.auth :title="__('Choose a new password')">
    <h1>{{ __('Choose a new password') }}</h1>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" type="email" :label="__('Email address')" :value="$email" required />
        <x-form.input name="password" type="password" :label="__('New password')" required autocomplete="new-password"
            :help="__('At least :n characters with upper and lower case letters, a number and a symbol.', ['n' => setting('password_min_length')])" />
        <x-form.input name="password_confirmation" type="password" :label="__('Confirm new password')" required autocomplete="new-password" />
        <button type="submit" class="btn btn-primary" style="width:100%">{{ __('Reset password') }}</button>
    </form>
</x-layouts.auth>
