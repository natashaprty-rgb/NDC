@php $editing = $task->exists; @endphp
<x-layout :title="$editing ? __('tasks.edit') : __('tasks.new')"
          :breadcrumbs="[__('tasks.title') => route('tasks.index'), ($editing ? $task->reference : __('tasks.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('tasks.update', $task) : route('tasks.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="title" :label="__('common.title')" :value="$task->title" :required="true" maxlength="200" class="span-2" />
                <x-field.select name="project_id" :label="__('common.project')" :options="$projects" :value="$task->project_id" :placeholder="__('common.none')" />
                <x-field.select name="milestone_id" :label="__('tasks.milestone')" :options="$milestones" :value="$task->milestone_id" :placeholder="__('common.none')" />
                <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="$task->department_id" :placeholder="__('common.not_set')" />
                <x-field.select name="priority" :label="__('tasks.priority')" :options="enum_options(\App\Models\Task::PRIORITIES)" :value="$task->priority" :required="true" :placeholder="false" />
                <x-field.input name="start_date" type="date" :label="__('tasks.start_date')" :value="$task->start_date" />
                <x-field.input name="due_date" type="date" :label="__('tasks.due_date')" :value="$task->due_date" />
                <x-field.select name="assigned_to" :label="__('common.assigned_to')" :options="$users" :value="$task->assigned_to" :placeholder="__('common.unassigned')" />
                <x-field.select name="approver_id" :label="__('tasks.approver')" :options="$users" :value="$task->approver_id" :placeholder="__('common.not_set')" :help="__('tasks.approver_help')" />
                <x-field.checkbox name="requires_approval" :label="__('tasks.requires_approval')" :checked="(bool) $task->requires_approval" class="span-2" />
                <x-field.textarea name="description" :label="__('common.description')" :value="$task->description" class="span-2" />
                <x-field.textarea name="deliverables" :label="__('tasks.deliverables')" :value="$task->deliverables" rows="2" class="span-2" />
            </div>
            <div class="form-actions">
                <a href="{{ $editing ? route('tasks.show', $task) : route('tasks.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
