@php
    $crumbs = [__('Tasks') => route('tasks.index')];
    if ($task->exists) {
        $crumbs[\Illuminate\Support\Str::limit($task->title, 40)] = route('tasks.show', $task);
    } elseif ($task->project_id && isset($projects[$task->project_id])) {
        $crumbs = [__('Projects') => route('projects.index'), $task->project->code => route('projects.show', [$task->project, 'tab' => 'tasks'])];
    }
@endphp
<x-layouts.app :title="$task->exists ? __('Edit task') : __('New task')">
    <x-page-header :title="$task->exists ? __('Edit task') : __('New task')" :breadcrumbs="$crumbs" />
    <form method="POST" action="{{ $task->exists ? route('tasks.update', $task) : route('tasks.store') }}" class="card form">
        @csrf
        @if ($task->exists) @method('PUT') @endif
        <div class="card__body">
            <div class="form-grid">
                <x-form.input name="title" :label="__('Task title')" :value="$task->title" required maxlength="255" class="span-2" />
                <x-form.textarea name="description" :label="__('Description')" :value="$task->description" class="span-2" />
                <x-form.textarea name="expected_deliverable" :label="__('Expected deliverable')" :value="$task->expected_deliverable" rows="3" class="span-2" />
                <x-form.select name="project_id" :label="__('Project')" :options="$projects" :value="$task->project_id" :placeholder="__('— No project —')" />
                <x-form.select name="milestone_id" :label="__('Milestone')" :options="$milestones" :value="$task->milestone_id" :placeholder="__('— No milestone —')"
                    :help="__('The milestone must belong to the selected project.')" />
            </div>
            @if ($task->exists)
                <p class="help">{{ __('Assigned to :assignee (:department). Use “Reassign” on the task page to change the assignment.', [
                    'assignee' => $task->assignee?->name ?? '—', 'department' => $task->department?->displayName() ?? '—']) }}</p>
            @else
                <fieldset>
                    <legend>{{ __('Assignment') }}</legend>
                    <div class="form-grid">
                        <x-form.select name="assigned_to" :label="__('Assign to staff member')" :options="$users" :value="$task->assigned_to" />
                        <x-form.select name="department_id" :label="__('Responsible department')" :options="$departments" :value="$task->department_id" />
                    </div>
                    <p class="help">{{ __('Choose a staff member, a department or both. The assignee is notified.') }}</p>
                </fieldset>
            @endif
            <fieldset>
                <legend>{{ __('Priority and deadline') }}</legend>
                <div class="form-grid">
                    <x-form.select name="priority" :label="__('Priority')" :options="\App\Support\Status::options('priority')" :value="$task->priority" required :placeholder="false" />
                    <div></div>
                    <x-form.input type="date" name="start_date" :label="__('Start date')" :value="$task->start_date" />
                    <x-form.input type="date" name="due_date" :label="__('Due date')" :value="$task->due_date" />
                    <x-form.checkbox name="requires_review" :label="__('Completed work must be reviewed and approved')" :checked="$task->requires_review" class="span-2" />
                </div>
            </fieldset>
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $task->exists ? route('tasks.show', $task) : ($task->project_id ? route('projects.show', [$task->project_id, 'tab' => 'tasks']) : route('tasks.index')) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
