<x-layouts.app :title="$department->exists ? __('Edit department') : __('New department')">
    <x-page-header :title="$department->exists ? $department->name : __('New department')" :breadcrumbs="[__('Departments') => route('admin.departments.index')]">
        @if ($department->exists)
            <x-slot:actions><x-delete-button :action="route('admin.departments.destroy', $department)" class="btn btn-danger" /></x-slot:actions>
        @endif
    </x-page-header>
    <form method="POST" action="{{ $department->exists ? route('admin.departments.update', $department) : route('admin.departments.store') }}" class="card form">
        @csrf
        @if ($department->exists) @method('PUT') @endif
        <div class="card__body form-grid">
            <x-form.input name="name" :label="__('Name (English)')" :value="$department->name" required />
            <x-form.input name="name_so" :label="__('Name (Somali)')" :value="$department->name_so" lang="so" />
            <x-form.input name="code" :label="__('Code')" :value="$department->code" required maxlength="20" :help="__('Short unique code, e.g. ICT or FIN.')" />
            <x-form.select name="parent_id" :label="__('Parent department')" :options="$parents" :value="$department->parent_id" />
            <x-form.select name="head_user_id" :label="__('Head of department')" :options="$users" :value="$department->head_user_id" />
            <x-form.input name="email" type="email" :label="__('Email')" :value="$department->email" />
            <x-form.input name="phone" :label="__('Phone')" :value="$department->phone" />
            <x-form.textarea name="description" :label="__('Description')" :value="$department->description" class="span-2" />
            <x-form.checkbox name="is_active" :label="__('Active')" :checked="$department->is_active" />
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ route('admin.departments.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
