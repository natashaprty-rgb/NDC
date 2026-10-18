@php $editing = $target->exists; @endphp
<x-layout :title="$editing ? __('users.edit') : __('users.new')"
          :breadcrumbs="[__('users.title') => route('admin.users.index'), ($editing ? $target->name : __('users.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('admin.users.update', $target) : route('admin.users.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="name" :label="__('common.name')" :value="$target->name" :required="true" maxlength="255" />
                <x-field.input name="email" type="email" :label="__('auth.email')" :value="$target->email" :required="true" />
                <x-field.select name="locale" :label="__('common.language')" :options="['en' => 'English', 'so' => 'Soomaali']" :value="$target->locale" :placeholder="false" :required="true" />
            </div>

            @if(! $editing)
                <fieldset class="mt-2">
                    <legend>{{ __('users.initial_password') }}</legend>
                    <label class="checkbox"><input type="radio" name="password_mode" value="generate" @checked(old('password_mode', 'generate') === 'generate')> {{ __('users.generate_password') }}</label>
                    <label class="checkbox"><input type="radio" name="password_mode" value="manual" @checked(old('password_mode') === 'manual')> {{ __('users.manual_password') }}</label>
                    <div class="form-grid mt-1">
                        <x-field.input name="password" type="password" :label="__('users.temporary_password')" :help="__('auth.password_rules')" autocomplete="new-password" />
                    </div>
                    <p class="small muted mb-0">{{ __('users.must_change_notice') }}</p>
                </fieldset>
            @endif

            <fieldset class="mt-2">
                <legend>{{ __('users.roles') }}</legend>
                @if($editing && auth()->id() === $target->id)
                    <p class="small muted">{{ __('users.cannot_change_own_roles') }}</p>
                @endif
                @error('roles')<p class="error-text">{{ $message }}</p>@enderror
                @php $chosen = collect(old('roles', $selected))->map(fn ($v) => (int) $v)->all(); @endphp
                <div class="grid grid-2">
                    @foreach($roles as $role)
                        <label class="checkbox">
                            <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, $chosen, true))>
                            <span><strong>{{ $role->name }}</strong><br><span class="small muted">{{ $role->description }}</span></span>
                        </label>
                    @endforeach
                </div>
                @foreach($selected as $sid)
                    @unless($roles->contains('id', $sid))
                        <input type="hidden" name="roles[]" value="{{ $sid }}">
                    @endunless
                @endforeach
            </fieldset>

            <div class="form-actions">
                <a href="{{ $editing ? route('admin.users.show', $target) : route('admin.users.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
