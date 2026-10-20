{{-- Navigation between the meeting list, calendar and outstanding action points. --}}
@php $current = $current ?? 'upcoming'; @endphp
<nav class="tabs" aria-label="{{ __('Meeting views') }}">
    @foreach ([
        'upcoming' => [__('Upcoming'), route('meetings.index')],
        'past' => [__('Past meetings'), route('meetings.index', ['when' => 'past'])],
        'all' => [__('All meetings'), route('meetings.index', ['when' => 'all'])],
        'calendar' => [__('Calendar'), route('meetings.calendar')],
        'actions' => [__('Outstanding action points'), route('meetings.action-items.index')],
    ] as $key => [$label, $url])
        <a href="{{ $url }}" @class(['is-active' => $current === $key]) @if($current === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
