@php $editing = $role->exists; @endphp
<x-layout :title="$editing ? __('roles.edit') : __('roles.new')"
          :breadcrumbs="[__('roles.title') => route('admin.roles.index'), ($editing ? $role->name : __('roles.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="name" :label="__('roles.name')" :value="$role->name" :required="true" maxlength="120" />
                <x-field.input name="description" :label="__('common.description')" :value="$role->description" maxlength="255" />
            </div>
            <h2 class="mt-2">{{ __('roles.permissions') }}</h2>
            @error('permissions')<p class="error-text">{{ $message }}</p>@enderror
            @include('admin.roles._matrix', ['editable' => true])
            <div class="form-actions">
                <a href="{{ $editing ? route('admin.roles.show', $role) : route('admin.roles.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
