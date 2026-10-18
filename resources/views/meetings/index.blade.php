<x-layout :title="__('meetings.title')" :breadcrumbs="[__('meetings.title')]">
    <x-slot:actions>
        <a href="{{ route('meetings.calendar') }}" class="btn"><x-icon name="calendar" width="16" height="16" /> {{ __('meetings.calendar') }}</a>
        @can('create', \App\Models\Meeting::class)
            <a href="{{ route('meetings.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('meetings.new') }}</a>
        @endcan
    </x-slot:actions>

    <div class="tabs">
        @foreach(['upcoming', 'past', 'all'] as $t)
            <a href="{{ route('meetings.index', array_merge(request()->except('page'), ['tab' => $t])) }}" class="{{ $tab === $t ? 'is-active' : '' }}">{{ __('meetings.tab_'.$t) }}</a>
        @endforeach
    </div>

    <section class="card">
        <x-filter-bar :action="route('meetings.index')">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('meetings.search_hint')" />
            <x-field.select name="type" :label="__('meetings.type')" :options="enum_options(\App\Models\Meeting::TYPES)" :value="request('type')" :placeholder="__('common.all')" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Meeting::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="department" :label="__('common.department')" :options="$departments" :value="request('department')" :placeholder="__('common.all')" />
            <x-field.select name="project" :label="__('common.project')" :options="$projects" :value="request('project')" :placeholder="__('common.all')" />
            <x-field.input name="from" type="date" :label="__('common.from')" :value="request('from')" />
            <x-field.input name="to" type="date" :label="__('common.to')" :value="request('to')" />
        </x-filter-bar>

        @if($meetings->isEmpty())
            <x-empty :message="request()->hasAny(['q', 'type', 'status', 'department', 'project', 'from', 'to']) ? __('common.no_records') : __('meetings.empty')" icon="calendar" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('meetings.when') }}</th><th>{{ __('meetings.meeting_title') }}</th><th>{{ __('meetings.type') }}</th>
                        <th>{{ __('meetings.location') }}</th><th>{{ __('meetings.organizer') }}</th><th class="num">{{ __('meetings.participants') }}</th><th>{{ __('common.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($meetings as $meeting)
                        <tr>
                            <td class="nowrap">{{ fmt_datetime($meeting->starts_at) }}</td>
                            <td><a href="{{ route('meetings.show', $meeting) }}">{{ $meeting->title }}</a><br><span class="small muted">{{ $meeting->reference }}@if($meeting->project) · {{ $meeting->project->name }}@endif</span></td>
                            <td>{{ status_label($meeting->meeting_type) }}</td>
                            <td>{{ $meeting->location ?? '—' }}</td>
                            <td>{{ $meeting->organizer?->name ?? '—' }}</td>
                            <td class="num">{{ $meeting->participants_count }}</td>
                            <td><x-badge :value="$meeting->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $meetings->links() }}
        @endif
    </section>
</x-layout>
