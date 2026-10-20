@php
    $attendees = $meeting->participants->sortBy(fn ($p) => [$p->user_id === null ? 1 : 0, $p->displayName()])->values();
    $decisions = $meeting->actionItems->where('type', 'decision')->values();
    $actions = $meeting->actionItems->where('type', 'action')->values();
@endphp
<x-layouts.app :title="__('Minutes: :title', ['title' => $meeting->title])">
    <x-page-header :title="__('Meeting minutes')" :breadcrumbs="[__('Meetings & Calendar') => route('meetings.index'), $meeting->title => route('meetings.show', $meeting)]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>

    <article class="card">
        <div class="card__body">
            <header style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;border-bottom:2px solid var(--brand-700);padding-bottom:.75rem;margin-bottom:1rem">
                <img src="{{ asset('images/logo-horizontal.png') }}" alt="{{ __('National Data Center Somalia') }}" style="max-height:56px;width:auto">
                <div class="text-right">
                    <strong>{{ setting('organisation_name', config('ndc.organisation')) }}</strong>
                    <div class="small muted">{{ __('Minutes of meeting') }}</div>
                </div>
            </header>

            <h2 style="font-size:1.35rem">{{ $meeting->title }}</h2>
            <dl class="dl">
                <dt>{{ __('Type') }}</dt><dd>{{ \App\Support\Status::label('meeting_type', $meeting->type) }}</dd>
                <dt>{{ __('Date and time') }}</dt><dd>{{ fmt_date($meeting->start_at, true) }}@if ($meeting->end_at) – {{ $meeting->end_at->isSameDay($meeting->start_at) ? $meeting->end_at->format('H:i') : fmt_date($meeting->end_at, true) }}@endif</dd>
                <dt>{{ __('Location') }}</dt><dd>{{ $meeting->location ?: '—' }}</dd>
                <dt>{{ __('Organizer') }}</dt><dd>{{ $meeting->organizer?->name ?? '—' }}</dd>
                <dt>{{ __('Department') }}</dt><dd>{{ $meeting->department?->displayName() ?? '—' }}</dd>
                @if ($meeting->project)<dt>{{ __('Related project') }}</dt><dd>{{ $meeting->project->code }} — {{ $meeting->project->title }}</dd>@endif
                <dt>{{ __('Status') }}</dt><dd>{{ \App\Support\Status::label('meeting', $meeting->status) }}</dd>
            </dl>

            <h3>{{ __('Attendees') }}</h3>
            @if ($attendees->isEmpty())
                <p class="muted">{{ __('No participants recorded.') }}</p>
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>#</th><th>{{ __('Name') }}</th><th>{{ __('Organization') }}</th><th>{{ __('Attendance') }}</th></tr></thead>
                    <tbody>
                    @foreach ($attendees as $participant)
                        <tr>
                            <td class="num">{{ $loop->iteration }}</td>
                            <td>{{ $participant->displayName() }}</td>
                            <td>{{ $participant->user ? setting('organisation_name', config('ndc.organisation')) : ($participant->organization ?: __('External guest')) }}</td>
                            <td>{{ \App\Support\Status::label('attendance', $participant->attendance) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif

            <h3>{{ __('Agenda') }}</h3>
            @if ($meeting->agenda)
                <div class="prose">{!! nl2br(e($meeting->agenda)) !!}</div>
            @else
                <p class="muted">{{ __('No agenda recorded.') }}</p>
            @endif

            <h3>{{ __('Minutes') }}</h3>
            @if ($meeting->minutes)
                <div class="prose">{!! nl2br(e($meeting->minutes)) !!}</div>
            @else
                <p class="muted">{{ __('Minutes have not been recorded yet.') }}</p>
            @endif

            <h3>{{ __('Decisions') }}</h3>
            @if ($decisions->isEmpty())
                <p class="muted">{{ __('No decisions recorded.') }}</p>
            @else
                <ol>
                    @foreach ($decisions as $decision)
                        <li>{!! nl2br(e($decision->description)) !!}@if ($decision->assignee) <span class="muted">— {{ $decision->assignee->name }}</span>@endif</li>
                    @endforeach
                </ol>
            @endif

            <h3>{{ __('Action points') }}</h3>
            @if ($actions->isEmpty())
                <p class="muted">{{ __('No action points recorded.') }}</p>
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>#</th><th>{{ __('Action') }}</th><th>{{ __('Responsible') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @foreach ($actions as $item)
                        <tr>
                            <td class="num">{{ $loop->iteration }}</td>
                            <td>{!! nl2br(e($item->description)) !!}</td>
                            <td>{{ $item->assignee?->name ?? '—' }}</td>
                            <td class="nowrap">{{ fmt_date($item->due_date) }}</td>
                            <td>{{ \App\Support\Status::label('action_item', $item->status) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif

            <footer class="small muted" style="margin-top:1.5rem;border-top:1px solid var(--line);padding-top:.5rem">
                @if ($meeting->minutes_recorded_at)
                    {{ __('Minutes recorded by :name on :date', ['name' => $meeting->minutesRecorder?->name ?? '—', 'date' => fmt_date($meeting->minutes_recorded_at, true)]) }} ·
                @endif
                {{ __('Printed :date', ['date' => fmt_date(now(), true)]) }}
            </footer>
        </div>
    </article>
</x-layouts.app>
