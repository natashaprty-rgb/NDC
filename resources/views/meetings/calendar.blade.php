<x-layout :title="__('meetings.calendar')" :subtitle="$month->translatedFormat('F Y')"
          :breadcrumbs="[__('meetings.title') => route('meetings.index'), __('meetings.calendar')]">
    <x-slot:actions>
        <a href="{{ route('meetings.calendar', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="btn">&lsaquo; {{ __('meetings.prev_month') }}</a>
        <a href="{{ route('meetings.calendar') }}" class="btn">{{ __('common.today') }}</a>
        <a href="{{ route('meetings.calendar', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="btn">{{ __('meetings.next_month') }} &rsaquo;</a>
        @can('create', \App\Models\Meeting::class)
            <a href="{{ route('meetings.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('meetings.new') }}</a>
        @endcan
    </x-slot:actions>

    <x-card :flush="true">
        <div class="table-wrap">
            <table class="calendar">
                <thead><tr>
                    @foreach(__('meetings.weekdays') as $dayName)
                        <th scope="col">{{ $dayName }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @foreach($weeks as $week)
                    <tr>
                        @foreach($week as $day)
                            @php $key = $day->toDateString(); @endphp
                            <td class="{{ $day->isSameMonth($month) ? '' : 'is-other' }} {{ $day->isToday() ? 'is-today' : '' }}" data-date="{{ $key }}">
                                <div class="day">{{ $day->day }}</div>
                                @foreach($byDay->get($key, collect()) as $event)
                                    <a href="{{ route('meetings.show', $event) }}" class="event" title="{{ $event->title }}">{{ $event->starts_at->format('H:i') }} {{ $event->title }}</a>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card :title="__('meetings.month_agenda')" :flush="true">
        @if($monthMeetings->isEmpty())
            <x-empty :message="__('meetings.no_meetings_month')" icon="calendar" />
        @else
            <div class="table-wrap"><table class="table"><tbody>
                @foreach($monthMeetings as $event)
                    <tr>
                        <td class="nowrap">{{ fmt_datetime($event->starts_at) }}</td>
                        <td><a href="{{ route('meetings.show', $event) }}">{{ $event->title }}</a></td>
                        <td>{{ $event->location ?? '—' }}</td>
                        <td><x-badge :value="$event->status" /></td>
                    </tr>
                @endforeach
            </tbody></table></div>
        @endif
    </x-card>
</x-layout>
