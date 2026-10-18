@php $editing = $department->exists; @endphp
<x-layout :title="$editing ? __('departments.edit') : __('departments.new')"
          :breadcrumbs="[__('departments.title') => route('departments.index'), ($editing ? $department->name : __('departments.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('departments.update', $department) : route('departments.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="code" :label="__('departments.code')" :value="$department->code" :required="true" maxlength="20" />
                <x-field.input name="name" :label="__('departments.name')" :value="$department->name" :required="true" maxlength="150" />
                <x-field.input name="name_so" :label="__('departments.name_so')" :value="$department->name_so" maxlength="150" />
                <x-field.select name="parent_id" :label="__('departments.parent')" :options="$parents" :value="$department->parent_id" :placeholder="__('common.none')" />
                <x-field.select name="head_user_id" :label="__('departments.head')" :options="$users" :value="$department->head_user_id" :placeholder="__('common.not_set')" />
                <x-field.input name="email" type="email" :label="__('departments.email')" :value="$department->email" />
                <x-field.input name="phone" :label="__('departments.phone')" :value="$department->phone" />
                <x-field.checkbox name="is_active" :label="__('departments.is_active')" :checked="$department->is_active" />
                <x-field.textarea name="description" :label="__('common.description')" :value="$department->description" class="span-2" />
            </div>
            <div class="form-actions">
                <a href="{{ $editing ? route('departments.show', $department) : route('departments.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
