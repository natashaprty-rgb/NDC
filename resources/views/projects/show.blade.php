@php
    $canUpdate = auth()->user()->can('update', $project);
    $tabs = [
        'overview' => [__('Overview'), null],
        'milestones' => [__('Milestones'), $project->milestones_count],
        'risks' => [__('Risks & challenges'), $project->risks_count],
        'stakeholders' => [__('Stakeholders'), $project->stakeholders_count],
        'team' => [__('Team'), $project->members_count],
        'tasks' => [__('Tasks'), $visibleTaskCount],
        'files' => [__('Documents'), $project->attachments_count],
        'history' => [__('History'), null],
    ];
@endphp
<x-layouts.app :title="$project->title">
    <x-page-header :title="$project->title" :breadcrumbs="[__('Projects') => route('projects.index')]">
        <x-slot:meta>
            <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-top:.4rem">
                <span class="mono small">{{ $project->code }}</span>
                <x-status domain="project" :value="$project->status" />
                @if ($project->isDelayed())<span class="badge badge--danger">{{ __('Delayed') }}</span>@endif
                @if ($project->isArchived())<span class="badge badge--neutral">{{ __('Archived') }}</span>@endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @if (! $project->isArchived() && Route::has('tasks.create'))
                @can('create', \App\Models\Task::class)
                    <a href="{{ route('tasks.create', ['project_id' => $project->id]) }}" class="btn"><x-icon name="plus" /> {{ __('New task') }}</a>
                @endcan
            @endif
            <a href="{{ route('projects.report', $project) }}" class="btn"><x-icon name="printer" /> {{ __('Progress report') }}</a>
            @if ($canUpdate)
                <a href="{{ route('projects.edit', $project) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endif
            @can('archive', $project)
                <x-delete-button :action="route('projects.archive', $project)" method="POST" :label="__('Archive')" class="btn"
                    :confirm="__('Archive this project? It will be hidden from the project list and become read-only.')" />
            @endcan
            @can('unarchive', $project)
                <x-delete-button :action="route('projects.unarchive', $project)" method="POST" :label="__('Restore from archive')" class="btn"
                    :confirm="__('Restore this project from the archive?')" />
            @endcan
            @can('delete', $project)
                <x-delete-button :action="route('projects.destroy', $project)" class="btn btn-danger"
                    :confirm="__('Delete this project? It will be removed from all lists. This cannot be undone from the interface.')" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($project->isArchived())
        <div class="alert alert--warning" role="status">{{ __('This project was archived on :date and is read-only.', ['date' => fmt_date($project->archived_at)]) }}</div>
    @endif

    <nav class="tabs" aria-label="{{ __('Project sections') }}">
        @foreach ($tabs as $key => [$label, $count])
            <a href="{{ route('projects.show', ['project' => $project, 'tab' => $key === 'overview' ? null : $key]) }}" @class(['is-active' => $tab === $key]) @if ($tab === $key) aria-current="page" @endif>
                {{ $label }}@if ($count !== null) <span class="muted">({{ $count }})</span>@endif
            </a>
        @endforeach
    </nav>

    @include('projects.partials.'.$tab)
</x-layouts.app>
