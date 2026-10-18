@php
    $canMinutes = auth()->user()->can('recordMinutes', $meeting);
    $canTask = auth()->user()->can('create', \App\Models\Task::class);
    $decisions = $meeting->items->where('type', 'decision');
    $actions = $meeting->items->where('type', 'action');
@endphp
<x-layout :title="$meeting->title" :subtitle="$meeting->reference.' · '.fmt_datetime($meeting->starts_at)"
          :breadcrumbs="[__('meetings.title') => route('meetings.index'), $meeting->title]">
    <x-slot:actions>
        @if($canMinutes)
            <a href="{{ route('meetings.minutes.edit', $meeting) }}" class="btn btn--primary"><x-icon name="clipboard" width="16" height="16" /> {{ __('meetings.record_minutes') }}</a>
        @endif
        @can('update', $meeting)
            <a href="{{ route('meetings.edit', $meeting) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
            @if($meeting->status === 'scheduled')
                <form method="POST" action="{{ route('meetings.status', $meeting) }}" class="inline-form" data-confirm="{{ __('meetings.confirm_postpone') }}">
                    @csrf<input type="hidden" name="status" value="postponed">
                    <button type="submit" class="btn">{{ __('meetings.postpone') }}</button>
                </form>
                <form method="POST" action="{{ route('meetings.status', $meeting) }}" class="inline-form" data-confirm="{{ __('meetings.confirm_cancel') }}">
                    @csrf<input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="btn btn--danger">{{ __('meetings.cancel_meeting') }}</button>
                </form>
            @elseif(in_array($meeting->status, ['cancelled', 'postponed'], true))
                <form method="POST" action="{{ route('meetings.status', $meeting) }}" class="inline-form">
                    @csrf<input type="hidden" name="status" value="scheduled">
                    <button type="submit" class="btn">{{ __('meetings.reopen') }}</button>
                </form>
            @endif
        @endcan
        @can('delete', $meeting)
            <x-delete-button :action="route('meetings.destroy', $meeting)" :label="__('common.archive')" :message="__('common.confirm_archive')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('meetings.agenda')">
                @if($meeting->agenda)<p class="prose mb-0">{{ $meeting->agenda }}</p>@else<p class="muted mb-0">{{ __('meetings.no_agenda') }}</p>@endif
                @if($meeting->description)<p class="prose mt-2 mb-0 small muted">{{ $meeting->description }}</p>@endif
            </x-card>

            <x-card :title="__('meetings.minutes')">
                @if($meeting->minutes)
                    <p class="prose">{{ $meeting->minutes }}</p>
                    <p class="small muted mb-0">{{ __('meetings.minutes_recorded', ['name' => \App\Models\User::withTrashed()->find($meeting->minutes_recorded_by)?->name ?? '—', 'date' => fmt_datetime($meeting->minutes_recorded_at)]) }}</p>
                @else
                    <p class="muted mb-0">{{ __('meetings.no_minutes') }}</p>
                @endif
            </x-card>

            <x-card :title="__('meetings.decisions')" :flush="true" id="items">
                @if($decisions->isEmpty())
                    <x-empty :message="__('meetings.no_decisions')" icon="check-circle" />
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach($decisions as $item)
                            <tr><td class="prose">{{ $item->description }}</td><td class="small muted nowrap">{{ fmt_datetime($item->created_at) }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-card>

            <x-card :title="__('meetings.actions')" :flush="true">
                @if($actions->isEmpty())
                    <x-empty :message="__('meetings.no_actions')" icon="list" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('meetings.item_description') }}</th><th>{{ __('meetings.responsible') }}</th><th>{{ __('meetings.due_date') }}</th><th>{{ __('common.status') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach($actions as $item)
                            @php $overdue = $item->status === 'open' && $item->due_date && $item->due_date->lt(today()); @endphp
                            <tr class="{{ $overdue ? 'row-overdue' : '' }}">
                                <td class="prose">{{ $item->description }}
                                    @if($item->task)<br><span class="small">{{ __('meetings.task_link') }}: @if(route_if('tasks.show', $item->task))<a href="{{ route('tasks.show', $item->task) }}">{{ $item->task->reference }}</a>@else{{ $item->task->reference }}@endif</span>@endif
                                </td>
                                <td>{{ $item->responsible?->name ?? '—' }}</td>
                                <td class="nowrap">{{ fmt_date($item->due_date) }} @if($overdue)<x-badge value="overdue" />@endif</td>
                                <td>
                                    @if($canMinutes)
                                        <form method="POST" action="{{ route('meetings.items.update', [$meeting, $item]) }}" class="flex">
                                            @csrf @method('PUT')
                                            <label class="sr-only" for="item_status_{{ $item->id }}">{{ __('common.status') }}</label>
                                            <select id="item_status_{{ $item->id }}" name="status" class="input" style="width:auto">
                                                @foreach(\App\Models\MeetingItem::STATUSES as $s)<option value="{{ $s }}" @selected($item->status === $s)>{{ status_label($s) }}</option>@endforeach
                                            </select>
                                            <button type="submit" class="btn btn--sm">{{ __('meetings.update_item') }}</button>
                                        </form>
                                    @else
                                        <x-badge :value="$item->status" />
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if($canMinutes && $canTask && ! $item->task_id && $item->responsible_id)
                                        <form method="POST" action="{{ route('meetings.items.task', [$meeting, $item]) }}" class="inline-form">
                                            @csrf<button type="submit" class="btn btn--sm">{{ __('meetings.convert_to_task') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                @if($canMinutes)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('meetings.items.store', $meeting) }}" class="form-grid">
                            @csrf
                            <x-field.select name="type" :label="__('meetings.item_type')" :options="enum_options(\App\Models\MeetingItem::TYPES)" value="action" :placeholder="false" />
                            <x-field.select name="responsible_id" :label="__('meetings.responsible')" :options="$users" :placeholder="__('common.not_set')" />
                            <x-field.textarea name="description" :label="__('meetings.item_description')" :required="true" rows="2" class="span-2" />
                            <x-field.input name="due_date" type="date" :label="__('meetings.due_date')" />
                            <div class="field" style="justify-content:flex-end"><button type="submit" class="btn btn--primary btn--sm">{{ __('meetings.add_item') }}</button></div>
                        </form>
                    </x-slot:footer>
                @endif
            </x-card>

            <x-attachments :model="$meeting" :categories="['agenda', 'minutes', 'general', 'report']" />
        </div>
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$meeting->status" /></dd>
                    <dt>{{ __('meetings.type') }}</dt><dd>{{ status_label($meeting->meeting_type) }}</dd>
                    <dt>{{ __('meetings.starts_at') }}</dt><dd>{{ fmt_datetime($meeting->starts_at) }}</dd>
                    <dt>{{ __('meetings.ends_at') }}</dt><dd>{{ fmt_datetime($meeting->ends_at) }}</dd>
                    <dt>{{ __('meetings.location') }}</dt><dd>{{ $meeting->location ?? '—' }}</dd>
                    <dt>{{ __('meetings.online_link') }}</dt><dd>@if($meeting->online_link)<a href="{{ $meeting->online_link }}" target="_blank" rel="noopener noreferrer">{{ \Illuminate\Support\Str::limit($meeting->online_link, 40) }}</a>@else — @endif</dd>
                    <dt>{{ __('meetings.organizer') }}</dt><dd>{{ $meeting->organizer?->name ?? '—' }}</dd>
                    <dt>{{ __('common.department') }}</dt><dd>{{ $meeting->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('common.project') }}</dt><dd>@if($meeting->project)@if(route_if('projects.show', $meeting->project))<a href="{{ route('projects.show', $meeting->project) }}">{{ $meeting->project->name }}</a>@else{{ $meeting->project->name }}@endif @else — @endif</dd>
                </dl>
            </x-card>

            <x-card :title="__('meetings.participants').' ('.$meeting->participants->count().')'" :flush="true">
                @if($meeting->participants->isEmpty())
                    <x-empty :message="__('meetings.no_participants')" icon="users" />
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach($meeting->participants as $p)
                            <tr>
                                <td>{{ $p->displayName() }}@if(! $p->user_id)<br><span class="small muted">{{ $p->external_organization ?? __('meetings.external') }}</span>@endif</td>
                                <td class="text-right"><x-badge :value="$p->attendance" /></td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-card>

            <x-history :model="$meeting" />
        </div>
    </div>
</x-layout>
