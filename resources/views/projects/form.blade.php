@php $editing = $project->exists; @endphp
<x-layout :title="$editing ? __('projects.edit') : __('projects.new')"
          :breadcrumbs="[__('projects.title') => route('projects.index'), ($editing ? $project->name : __('projects.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('projects.update', $project) : route('projects.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="name" :label="__('projects.name')" :value="$project->name" :required="true" maxlength="200" class="span-2" />
                <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Project::STATUSES)" :value="$project->status" :required="true" :placeholder="false" />
                <x-field.select name="priority" :label="__('projects.priority')" :options="enum_options(\App\Models\Project::PRIORITIES)" :value="$project->priority" :required="true" :placeholder="false" />
                <x-field.select name="manager_id" :label="__('projects.manager')" :options="$users" :value="$project->manager_id" :placeholder="__('common.not_set')" />
                <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="$project->department_id" :placeholder="__('common.not_set')" />
                <x-field.input name="start_date" type="date" :label="__('projects.start_date')" :value="$project->start_date" />
                <x-field.input name="expected_end_date" type="date" :label="__('projects.expected_end_date')" :value="$project->expected_end_date" />
                <x-field.input name="actual_end_date" type="date" :label="__('projects.actual_end_date')" :value="$project->actual_end_date" />
                <div></div>
                <x-field.textarea name="description" :label="__('common.description')" :value="$project->description" class="span-2" />
                <x-field.textarea name="objectives" :label="__('projects.objectives')" :value="$project->objectives" class="span-2" />
            </div>
            <p class="small muted mt-1 mb-0">{{ __('projects.progress_note') }}</p>
            <div class="form-actions">
                <a href="{{ $editing ? route('projects.show', $project) : route('projects.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
