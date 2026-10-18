<x-layout :title="__('auth.change_password')" :breadcrumbs="[__('auth.change_password')]">
    <x-card style="max-width:560px">
        <form method="POST" action="{{ route('password.change.update') }}">
            @csrf
            @method('PUT')
            <div class="stack">
                <x-field.input name="current_password" type="password" :label="__('auth.current_password')" :required="true" autocomplete="current-password" />
                <x-field.input name="password" type="password" :label="__('auth.new_password')" :required="true" :help="__('auth.password_rules')" autocomplete="new-password" />
                <x-field.input name="password_confirmation" type="password" :label="__('auth.confirm_password')" :required="true" autocomplete="new-password" />
            </div>
            <div class="form-actions"><button type="submit" class="btn btn--primary">{{ __('auth.change_password') }}</button></div>
        </form>
    </x-card>
</x-layout>
