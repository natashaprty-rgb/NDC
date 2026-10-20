@php
    $user = auth()->user();
    $canUpdate = $user->can('update', $meeting);
    $internal = $meeting->participants->whereNotNull('user_id')->sortBy(fn ($p) => $p->displayName())->values();
    $external = $meeting->participants->whereNull('user_id')->values();
    $statusActions = [
        'completed' => [__('Mark as completed'), 'btn-success'],
        'postponed' => [__('Postpone'), ''],
        'cancelled' => [__('Cancel meeting'), 'btn-danger'],
        'scheduled' => [__('Reschedule (set as scheduled)'), ''],
    ];
@endphp
<x-layouts.app :title="$meeting->title">
    <x-page-header :title="$meeting->title" :breadcrumbs="[__('Meetings & Calendar') => route('meetings.index')]">
        <x-slot:meta>
            <p>
                <x-status domain="meeting_type" :value="$meeting->type" />
                <x-status domain="meeting" :value="$meeting->status" />
                <span class="muted">{{ fmt_date($meeting->start_at, true) }}@if ($meeting->end_at) – {{ $meeting->end_at->isSameDay($meeting->start_at) ? $meeting->end_at->format('H:i') : fmt_date($meeting->end_at, true) }}@endif</span>
            </p>
        </x-slot:meta>
        <x-slot:actions>
            <a href="{{ route('meetings.minutes', $meeting) }}" class="btn"><x-icon name="printer" /> {{ __('Printable minutes') }}</a>
            @if ($canUpdate)
                <a href="{{ route('meetings.edit', $meeting) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endif
            @can('delete', $meeting)
                <x-delete-button :action="route('meetings.destroy', $meeting)" :label="__('Delete')" class="btn btn-danger" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Meeting details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Starts') }}</dt><dd>{{ fmt_date($meeting->start_at, true) }}</dd>
                        <dt>{{ __('Ends') }}</dt><dd>{{ fmt_date($meeting->end_at, true) }}</dd>
                        <dt>{{ __('Location') }}</dt><dd>{{ $meeting->location ?: '—' }}</dd>
                        <dt>{{ __('Organizer') }}</dt><dd>{{ $meeting->organizer?->name ?? '—' }}</dd>
                        <dt>{{ __('Department') }}</dt><dd>{{ $meeting->department?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Related project') }}</dt>
                        <dd>
                            @if ($meeting->project)
                                @if (Route::has('projects.show') && $user->can('projects.view'))<a href="{{ route('projects.show', $meeting->project) }}">{{ $meeting->project->code }} — {{ $meeting->project->title }}</a>@else{{ $meeting->project->code }} — {{ $meeting->project->title }}@endif
                            @else — @endif
                        </dd>
                    </dl>
                    @if ($meeting->description)
                        <h3>{{ __('Description') }}</h3>
                        <div class="prose">{!! nl2br(e($meeting->description)) !!}</div>
                    @endif
                </div>
            </div>

            <div class="card" id="agenda">
                <div class="card__head"><h2>{{ __('Agenda') }}</h2></div>
                <div class="card__body">
                    @if ($meeting->agenda)
                        <div class="prose">{!! nl2br(e($meeting->agenda)) !!}</div>
                    @else
                        <p class="muted mb-0">{{ __('No agenda has been prepared yet.') }}</p>
                    @endif
                </div>
            </div>

            <div class="card" id="participants">
                <div class="card__head">
                    <h2>{{ __('Participants and attendance') }}</h2>
                    <span class="muted small">{{ __(':internal staff · :external guests', ['internal' => $internal->count(), 'external' => $external->count()]) }}</span>
                </div>
                @if ($meeting->participants->isEmpty())
                    <x-empty icon="users" :message="__('No participants have been invited.')" />
                @else
                    <form method="POST" action="{{ route('meetings.attendance', $meeting) }}">
                        @csrf @method('PUT')
                        <div class="table-wrap"><table class="table">
                            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Organization') }}</th><th>{{ __('Attendance') }}</th></tr></thead>
                            <tbody>
                            @foreach ($internal->concat($external) as $participant)
                                <tr>
                                    <td class="title-cell">{{ $participant->displayName() }}
                                        <span class="sub">{{ $participant->user ? __('Staff') : __('External guest') }}@if ($participant->email ?? $participant->user?->email) · {{ $participant->email ?? $participant->user?->email }}@endif</span></td>
                                    <td>{{ $participant->user ? ($participant->user->department?->displayName() ?? __('National Data Center Somalia')) : ($participant->organization ?: '—') }}</td>
                                    <td>
                                        @if ($canUpdate)
                                            <label class="sr-only" for="att_{{ $participant->id }}">{{ __('Attendance of :name', ['name' => $participant->displayName()]) }}</label>
                                            <select id="att_{{ $participant->id }}" name="attendance[{{ $participant->id }}]" class="select">
                                                @foreach (\App\Support\Status::options('attendance') as $value => $label)
                                                    <option value="{{ $value }}" @selected($participant->attendance === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <x-status domain="attendance" :value="$participant->attendance" />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                        @if ($canUpdate)
                            <div class="card__foot form-actions">
                                <button type="submit" class="btn btn-primary">{{ __('Save attendance') }}</button>
                                <span class="small muted">{{ __('Record attendance after the meeting has taken place.') }}</span>
                            </div>
                        @endif
                    </form>
                @endif
            </div>

            <div class="card" id="minutes">
                <div class="card__head">
                    <h2>{{ __('Minutes') }}</h2>
                    @if ($meeting->minutes_recorded_at)
                        <span class="muted small">{{ __('Recorded by :name on :date', ['name' => $meeting->minutesRecorder?->name ?? '—', 'date' => fmt_date($meeting->minutes_recorded_at, true)]) }}</span>
                    @endif
                </div>
                <div class="card__body">
                    @if ($canUpdate)
                        <form method="POST" action="{{ route('meetings.minutes.update', $meeting) }}">
                            @csrf @method('PUT')
                            <x-form.textarea name="minutes" :label="__('Minutes of the meeting')" :value="$meeting->minutes" rows="10" required maxlength="100000" />
                            @if ($meeting->status === 'scheduled')
                                <x-form.checkbox name="mark_completed" :label="__('Also mark the meeting as completed')" :checked="$meeting->start_at->isPast()" />
                            @endif
                            <button type="submit" class="btn btn-primary">{{ __('Save minutes') }}</button>
                        </form>
                    @elseif ($meeting->minutes)
                        <div class="prose">{!! nl2br(e($meeting->minutes)) !!}</div>
                    @else
                        <p class="muted mb-0">{{ __('Minutes have not been recorded yet.') }}</p>
                    @endif
                </div>
            </div>

            <div class="card" id="action-items">
                <div class="card__head"><h2>{{ __('Decisions and action points') }}</h2></div>
                @if ($meeting->actionItems->isEmpty())
                    <x-empty icon="check" :message="__('No decisions or action points recorded.')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th>{{ __('Assigned to') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($meeting->actionItems as $item)
                            <tr>
                                <td><x-status domain="action_item_type" :value="$item->type" /></td>
                                <td>{!! nl2br(e($item->description)) !!}@if ($item->notes)<span class="sub muted small">{{ $item->notes }}</span>@endif</td>
                                <td>{{ $item->assignee?->name ?? '—' }}</td>
                                <td class="nowrap">{{ fmt_date($item->due_date) }} @if ($item->isOverdue())<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</td>
                                <td>
                                    @include('meetings.partials.item-status', ['item' => $item])
                                </td>
                                <td class="actions nowrap">
                                    @can('update', $item)
                                        <a href="{{ route('meetings.action-items.edit', $item) }}" class="btn btn-sm btn-ghost" aria-label="{{ __('Edit') }}"><x-icon name="edit" /></a>
                                        <x-delete-button :action="route('meetings.action-items.destroy', $item)" :label="__('Remove')" class="btn-link danger" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                @if ($canUpdate)
                    <div class="card__foot">
                        <form method="POST" action="{{ route('meetings.action-items.store', $meeting) }}">
                            @csrf
                            <h3>{{ __('Add a decision or action point') }}</h3>
                            <div class="form-grid">
                                <x-form.select name="type" :label="__('Type')" :options="\App\Support\Status::options('action_item_type')" value="action" required :placeholder="false" id="ai_type" />
                                <x-form.select name="assigned_to" :label="__('Assigned to')" :options="$users" id="ai_assigned" />
                                <x-form.textarea name="description" :label="__('Description')" rows="2" required class="span-2" maxlength="5000" id="ai_description" />
                                <x-form.input name="due_date" type="date" :label="__('Due date')" id="ai_due" />
                                <x-form.input name="notes" :label="__('Notes')" maxlength="5000" id="ai_notes" />
                            </div>
                            <button type="submit" class="btn btn-primary"><x-icon name="plus" /> {{ __('Add') }}</button>
                        </form>
                    </div>
                @endif
            </div>

            <x-attachments :model="$meeting" :can-upload="$canUpdate" :title="__('Supporting documents')" />
            <x-audit-trail :model="$meeting" />
        </div>

        <div class="stack">
            @if ($canUpdate)
                <div class="card">
                    <div class="card__head"><h2>{{ __('Meeting status') }}</h2></div>
                    <div class="card__body">
                        <form method="POST" action="{{ route('meetings.status', $meeting) }}" class="stack">
                            @csrf @method('PATCH')
                            <x-form.input name="reason" :label="__('Reason (optional)')" maxlength="1000" />
                            @foreach ($statusActions as $value => [$label, $class])
                                @if ($value !== $meeting->status)
                                    <button type="submit" name="status" value="{{ $value }}" class="btn {{ $class }}" style="width:100%">{{ $label }}</button>
                                @endif
                            @endforeach
                        </form>
                        <p class="small muted">{{ __('Participants are notified when a meeting is postponed or cancelled.') }}</p>
                    </div>
                </div>
            @endif
            <div class="card">
                <div class="card__head"><h2>{{ __('Summary') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Attended') }}</dt><dd>{{ $meeting->participants->where('attendance', 'attended')->count() }} / {{ $meeting->participants->count() }}</dd>
                        <dt>{{ __('Decisions') }}</dt><dd>{{ $meeting->actionItems->where('type', 'decision')->count() }}</dd>
                        <dt>{{ __('Action points') }}</dt><dd>{{ $meeting->actionItems->where('type', 'action')->count() }}</dd>
                        <dt>{{ __('Outstanding') }}</dt><dd>{{ $meeting->actionItems->whereIn('status', ['open', 'in_progress'])->count() }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
