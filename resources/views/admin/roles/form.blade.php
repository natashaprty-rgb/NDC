<x-layouts.app :title="$role->exists ? __('Edit role') : __('New role')">
    <x-page-header :title="$role->exists ? __($role->name) : __('New role')" :breadcrumbs="[__('Roles & Permissions') => route('admin.roles.index')]" />
    <form method="POST" action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="card">
        @csrf
        @if ($role->exists) @method('PUT') @endif
        <div class="card__body">
            <div style="max-width:420px"><x-form.input name="name" :label="__('Role name')" :value="$role->name" required /></div>
            @foreach (config('ndc.permissions') as $module => $permissions)
                <fieldset>
                    <legend>{{ __(ucfirst($module)) }}</legend>
                    <div class="checks">
                        @foreach ($permissions as $name => $label)
                            <label class="check"><input type="checkbox" name="permissions[]" value="{{ $name }}" @checked(in_array($name, old('permissions', $granted), true))>
                                <span>{{ __($label) }} <span class="muted small mono">{{ $name }}</span></span></label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save role') }}</button>
            <a href="{{ route('admin.roles.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
