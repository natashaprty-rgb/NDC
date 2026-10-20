<x-layouts.app :title="__('Projects')">
    <x-page-header :title="__('Projects')" :subtitle="__('Projects, milestones, risks and implementation progress.')">
        @can('create', \App\Models\Project::class)
            <x-slot:actions>
                <a href="{{ route('projects.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('New project') }}</a>
            </x-slot:actions>
        @endcan
    </x-page-header>
    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Code, title or location')" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('project')" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="manager" :label="__('Project manager')" :options="$managers" />
            <x-filter-select name="delayed" :label="__('Schedule')" :options="['1' => __('Delayed only')]" />
            <div class="field">
                <label for="flt_archived">{{ __('Archive') }}</label>
                <select id="flt_archived" name="archived" class="select">
                    <option value="">{{ __('Active projects') }}</option>
                    <option value="archived" @selected(request('archived') === 'archived')>{{ __('Archived projects') }}</option>
                    <option value="all" @selected(request('archived') === 'all')>{{ __('All projects') }}</option>
                </select>
            </div>
        </x-filters>
        @if ($projects->isEmpty())
            <x-empty icon="folder" :message="__('No projects found.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="code" :label="__('Code')" /></th>
                    <th><x-sort-link column="title" :label="__('Title')" /></th>
                    <th>{{ __('Project manager') }}</th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                    <th style="min-width:140px"><x-sort-link column="progress" :label="__('Progress')" /></th>
                    <th><x-sort-link column="expected_end_date" :label="__('Expected completion')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($projects as $project)
                    <tr>
                        <td class="mono nowrap">{{ $project->code }}</td>
                        <td class="title-cell"><a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
                            <span class="sub">{{ $project->department?->displayName() ?? '—' }}@if ($project->location) · {{ $project->location }}@endif</span></td>
                        <td>{{ $project->manager?->name ?? '—' }}</td>
                        <td>
                            <x-status domain="project" :value="$project->status" />
                            @if ($project->isDelayed())<span class="badge badge--danger">{{ __('Delayed') }}</span>@endif
                            @if ($project->isArchived())<span class="badge badge--neutral">{{ __('Archived') }}</span>@endif
                        </td>
                        <td><x-progress :value="$project->progress" :tone="$project->isDelayed() ? 'danger' : null" /></td>
                        <td class="nowrap">{{ fmt_date($project->expected_end_date) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $projects->links() }}
    </div>
</x-layouts.app>
