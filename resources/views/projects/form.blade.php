@php
    $crumbs = [__('Projects') => route('projects.index')];
    if ($project->exists) {
        $crumbs[$project->code] = route('projects.show', $project);
    }
@endphp
<x-layouts.app :title="$project->exists ? __('Edit project') : __('New project')">
    <x-page-header :title="$project->exists ? __('Edit project') : __('New project')" :subtitle="$project->exists ? $project->code.' — '.$project->title : null" :breadcrumbs="$crumbs" />
    <form method="POST" action="{{ $project->exists ? route('projects.update', $project) : route('projects.store') }}" class="card form">
        @csrf
        @if ($project->exists) @method('PUT') @endif
        <div class="card__body">
            <div class="form-grid">
                <x-form.input name="title" :label="__('Project title')" :value="$project->title" required maxlength="255" class="span-2" />
                <x-form.input name="code" :label="__('Project code')" :value="$project->code" :required="$project->exists" maxlength="40"
                    :help="$project->exists ? null : __('Leave blank to generate the next code automatically (e.g. NDC/PRJ/:year/0001).', ['year' => now()->year])" />
                <x-form.input name="location" :label="__('Location')" :value="$project->location" maxlength="255" />
                <x-form.select name="department_id" :label="__('Responsible department')" :options="$departments" :value="$project->department_id" />
                <x-form.select name="manager_id" :label="__('Project manager')" :options="$users" :value="$project->manager_id" />
                <x-form.select name="status" :label="__('Status')" :options="\App\Support\Status::options('project')" :value="$project->status" required :placeholder="false" />
                <x-form.select name="priority" :label="__('Priority')" :options="\App\Support\Status::options('priority')" :value="$project->priority" required :placeholder="false" />
                <x-form.textarea name="description" :label="__('Description')" :value="$project->description" class="span-2" />
                <x-form.textarea name="objectives" :label="__('Objectives')" :value="$project->objectives" class="span-2" />
            </div>
            <fieldset>
                <legend>{{ __('Schedule and progress') }}</legend>
                <div class="form-grid">
                    <x-form.input type="date" name="start_date" :label="__('Start date')" :value="$project->start_date" />
                    <x-form.input type="date" name="expected_end_date" :label="__('Expected completion date')" :value="$project->expected_end_date" />
                    <x-form.input type="date" name="actual_end_date" :label="__('Actual completion date')" :value="$project->actual_end_date"
                        :help="__('Filled in automatically with today\'s date when the project is marked as completed.')" />
                    <x-form.input type="number" name="progress" :label="__('Progress (%)')" :value="$project->progress" required min="0" max="100" step="1" />
                </div>
            </fieldset>
            <fieldset>
                <legend>{{ __('Budget') }}</legend>
                <div class="form-grid">
                    <x-form.input type="number" name="planned_budget" :label="__('Planned budget')" :value="$project->planned_budget" min="0" step="0.01" />
                    <x-form.input name="currency" :label="__('Currency')" :value="$project->currency" required maxlength="3" :help="__('Three-letter code, e.g. USD.')" />
                </div>
            </fieldset>
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $project->exists ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
