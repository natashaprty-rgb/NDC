<x-layout :title="__('projects.title')" :breadcrumbs="[__('projects.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Project::class)
            <a href="{{ route('projects.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('projects.new') }}</a>
        @endcan
    </x-slot:actions>

    <section class="card">
        <x-filter-bar :action="route('projects.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('projects.search_hint')" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Project::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="department" :label="__('common.department')" :options="$departments" :value="request('department')" :placeholder="__('common.all')" />
            <x-field.select name="manager" :label="__('projects.manager')" :options="$managers" :value="request('manager')" :placeholder="__('common.all')" />
            <div class="field"><span class="label">&nbsp;</span>
                <label class="checkbox"><input type="checkbox" name="archived" value="1" @checked(request()->boolean('archived'))> {{ __('projects.include_archived') }}</label>
            </div>
        </x-filter-bar>

        @if($projects->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'status', 'department', 'manager', 'archived']) ? __('common.no_records') : __('projects.empty')" icon="briefcase" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('common.reference') }}</th><th>{{ __('projects.name') }}</th><th>{{ __('projects.manager') }}</th>
                        <th>{{ __('common.department') }}</th><th>{{ __('common.status') }}</th><th>{{ __('projects.expected_end_date') }}</th><th>{{ __('common.progress') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($projects as $project)
                        <tr class="{{ $project->isOverdue() ? 'row-overdue' : '' }}">
                            <td class="nowrap">{{ $project->reference }}</td>
                            <td>
                                <a href="{{ route('projects.show', $project) }}">{{ $project->name }}</a>
                                @if($project->isArchived()) <span class="badge">{{ __('projects.archived_badge') }}</span>@endif
                            </td>
                            <td>{{ $project->manager?->name ?? '—' }}</td>
                            <td>{{ $project->department?->displayName() ?? '—' }}</td>
                            <td><x-badge :value="$project->status" /></td>
                            <td class="nowrap">{{ fmt_date($project->expected_end_date) }} @if($project->isOverdue())<x-badge value="overdue" />@endif</td>
                            <td style="min-width:140px"><x-progress :value="$project->progressPercent()" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $projects->links() }}
        @endif
    </section>
</x-layout>
