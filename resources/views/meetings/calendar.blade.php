@php
    $locale = app()->getLocale();
    $monthLabel = $month->copy()->locale($locale)->isoFormat('MMMM YYYY');
    $mine = request()->boolean('mine');
@endphp
<x-layouts.app :title="__('Calendar')">
    <x-page-header :title="__('Meetings & Calendar')" :subtitle="__('Meetings, appointments, official events and action point deadlines.')">
        <x-slot:actions>
            @can('create', \App\Models\Meeting::class)
                <a href="{{ route('meetings.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('Schedule meeting') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @include('meetings.partials.tabs', ['current' => 'calendar'])

    <div class="card">
        <div class="card__head">
            <h2>{{ $monthLabel }}</h2>
            <div style="display:flex;gap:.35rem;flex-wrap:wrap;align-items:center">
                <a href="{{ route('meetings.calendar', array_filter(['month' => $prev, 'mine' => $mine ?: null])) }}" class="btn btn-sm" rel="prev"><x-icon name="chevron-left" /> <span>{{ __('Previous month') }}</span></a>
                <a href="{{ route('meetings.calendar', array_filter(['mine' => $mine ?: null])) }}" class="btn btn-sm">{{ __('Today') }}</a>
                <a href="{{ route('meetings.calendar', array_filter(['month' => $next, 'mine' => $mine ?: null])) }}" class="btn btn-sm" rel="next"><span>{{ __('Next month') }}</span> <x-icon name="chevron-right" /></a>
                <a href="{{ route('meetings.calendar', array_filter(['month' => $month->format('Y-m'), 'mine' => $mine ? null : 1])) }}" class="btn btn-sm btn-ghost" aria-pressed="{{ $mine ? 'true' : 'false' }}">{{ $mine ? __('Show everything') : __('Only mine') }}</a>
            </div>
        </div>
        <div class="card__body">
            <div class="cal" role="grid" aria-label="{{ $monthLabel }}">
                @foreach (array_slice($days, 0, 7) as $dow)
                    <div class="cal__dow" role="columnheader">{{ $dow->copy()->locale($locale)->isoFormat('ddd') }}</div>
                @endforeach
                @foreach ($days as $day)
                    @php
                        $key = $day->toDateString();
                        $dayMeetings = $meetings->get($key, collect());
                        $dayDeadlines = $deadlines->get($key, collect());
                    @endphp
                    <div @class(['cal__day', 'is-other' => ! $day->isSameMonth($month), 'is-today' => $day->isToday()]) role="gridcell" aria-label="{{ $day->copy()->locale($locale)->isoFormat('LL') }}">
                        <span class="cal__num">{{ $day->day }}</span>
                        @foreach ($dayMeetings as $meeting)
                            <a href="{{ route('meetings.show', $meeting) }}" class="cal__event" title="{{ $meeting->start_at->format('H:i') }} {{ $meeting->title }}@if($meeting->location) — {{ $meeting->location }}@endif · {{ \App\Support\Status::label('meeting', $meeting->status) }}"
                               @if (in_array($meeting->status, ['cancelled', 'postponed'], true)) style="text-decoration:line-through;opacity:.7" @endif>
                                {{ $meeting->start_at->format('H:i') }} {{ $meeting->title }}
                            </a>
                        @endforeach
                        @foreach ($dayDeadlines as $item)
                            <a href="{{ route('meetings.show', $item->meeting_id) }}#action-items" class="cal__event cal__event--deadline" title="{{ __('Action point due') }}: {{ $item->description }}@if($item->assignee) ({{ $item->assignee->name }})@endif">
                                <x-icon name="clock" /> {{ \Illuminate\Support\Str::limit($item->description, 40) }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <p class="small muted" style="margin-top:.75rem">
                <span class="cal__event" style="display:inline-block">{{ __('Meeting or event') }}</span>
                <span class="cal__event cal__event--deadline" style="display:inline-block">{{ __('Action point deadline') }}</span>
            </p>
        </div>
    </div>
</x-layouts.app>
