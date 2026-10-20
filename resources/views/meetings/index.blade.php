<x-layouts.app :title="__('Meetings & Calendar')">
    <x-page-header :title="__('Meetings & Calendar')" :subtitle="__('Meetings, appointments and official events, with minutes and action points.')">
        <x-slot:actions>
            @can('create', \App\Models\Meeting::class)
                <a href="{{ route('meetings.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('Schedule meeting') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @include('meetings.partials.tabs', ['current' => $when])

    @if ($outstanding > 0)
        <div class="alert alert--info" role="status">
            {{ trans_choice(':count action point is outstanding across all meetings.|:count action points are outstanding across all meetings.', $outstanding) }}
            <a href="{{ route('meetings.action-items.index') }}">{{ __('View action points') }}</a>
        </div>
    @endif

    <div class="card">
        <x-filters>
            <input type="hidden" name="when" value="{{ $when }}">
            <x-search-field :placeholder="__('Title, location or description')" />
            <x-filter-select name="type" :label="__('Type')" :options="\App\Support\Status::options('meeting_type')" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('meeting')" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="project" :label="__('Project')" :options="$projects" />
            <div class="field" style="flex:0 0 auto">
                <label class="check" for="flt_mine"><input type="checkbox" id="flt_mine" name="mine" value="1" @checked(request()->boolean('mine'))> {{ __('Only meetings I attend or organise') }}</label>
            </div>
        </x-filters>

        @if ($meetings->isEmpty())
            <x-empty icon="calendar" :message="$when === 'upcoming' ? __('No upcoming meetings.') : __('No meetings found.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="start_at" :label="__('Date and time')" /></th>
                    <th><x-sort-link column="title" :label="__('Title')" /></th>
                    <th><x-sort-link column="type" :label="__('Type')" /></th>
                    <th>{{ __('Location') }}</th>
                    <th>{{ __('Organizer') }}</th>
                    <th class="num">{{ __('Participants') }}</th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($meetings as $meeting)
                    <tr>
                        <td class="nowrap">
                            {{ fmt_date($meeting->start_at, true) }}
                            @if ($meeting->end_at)<span class="sub muted small">– {{ $meeting->end_at->isSameDay($meeting->start_at) ? $meeting->end_at->format('H:i') : fmt_date($meeting->end_at, true) }}</span>@endif
                        </td>
                        <td class="title-cell">
                            <a href="{{ route('meetings.show', $meeting) }}">{{ $meeting->title }}</a>
                            @if ($meeting->department)<span class="sub">{{ $meeting->department->displayName() }}</span>@endif
                        </td>
                        <td><x-status domain="meeting_type" :value="$meeting->type" /></td>
                        <td>{{ $meeting->location ?: '—' }}</td>
                        <td>{{ $meeting->organizer?->name ?? '—' }}</td>
                        <td class="num">{{ $meeting->participants_count }}</td>
                        <td><x-status domain="meeting" :value="$meeting->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $meetings->links() }}
    </div>
</x-layouts.app>
