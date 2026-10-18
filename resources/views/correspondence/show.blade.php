<x-layout :title="$item->subject" :subtitle="$item->reference.' · '.status_label($item->direction)"
          :breadcrumbs="[__('correspondence.title') => route('correspondence.index'), $item->reference]">
    <x-slot:actions>
        @can('create', \App\Models\Correspondence::class)
            <a href="{{ route('correspondence.create', ['direction' => $item->direction === 'incoming' ? 'outgoing' : 'incoming', 'reply_to' => $item->id]) }}" class="btn">{{ __('correspondence.register_reply') }}</a>
        @endcan
        @can('update', $item)
            <a href="{{ route('correspondence.edit', $item) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('delete', $item)
            <x-delete-button :action="route('correspondence.destroy', $item)" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    @if($item->isOverdue())
        <div class="alert alert--danger" role="status">{{ __('correspondence.overdue_notice', ['date' => fmt_date($item->response_deadline)]) }}</div>
    @endif

    <div class="layout-main-side">
        <div>
            @if($item->summary)
                <x-card :title="__('correspondence.summary')"><p class="prose mb-0">{{ $item->summary }}</p></x-card>
            @endif

            @can('act', $item)
                <x-card :title="__('correspondence.actions')">
                    <div class="grid grid-3">
                        <form method="POST" action="{{ route('correspondence.forward', $item) }}" class="stack">
                            @csrf
                            <h3>{{ __('correspondence.forward') }}</h3>
                            <x-field.select name="to_user_id" :label="__('correspondence.forward_to_user')" :options="$users" :placeholder="__('common.none')" />
                            <x-field.select name="to_department_id" :label="__('correspondence.forward_to_department')" :options="$departments" :placeholder="__('common.none')" />
                            <x-field.textarea name="note" :label="__('common.notes')" :rows="2" />
                            <button type="submit" class="btn btn--primary btn--sm">{{ __('correspondence.forward') }}</button>
                        </form>
                        <form method="POST" action="{{ route('correspondence.follow-up', $item) }}" class="stack">
                            @csrf
                            <h3>{{ __('correspondence.follow_up') }}</h3>
                            <x-field.input name="follow_up_date" type="date" :label="__('correspondence.follow_up_date')" />
                            <x-field.textarea name="note" :label="__('common.notes')" :rows="2" :required="true" />
                            <button type="submit" class="btn btn--primary btn--sm">{{ __('correspondence.record_follow_up') }}</button>
                        </form>
                        <form method="POST" action="{{ route('correspondence.status', $item) }}" class="stack">
                            @csrf
                            <h3>{{ __('correspondence.change_status') }}</h3>
                            <x-field.select name="status" :label="__('common.status')" :options="enum_options(array_values(array_diff(\App\Models\Correspondence::STATUSES, [$item->status])))" :required="true" />
                            <x-field.textarea name="note" :label="__('common.notes')" :rows="2" />
                            <button type="submit" class="btn btn--primary btn--sm">{{ __('correspondence.update_status') }}</button>
                        </form>
                    </div>
                </x-card>
            @endcan

            <x-card :title="__('correspondence.timeline')">
                @if($item->actions->isEmpty())
                    <p class="muted mb-0">{{ __('correspondence.no_actions') }}</p>
                @else
                    <ul class="timeline">
                        @foreach($item->actions as $action)
                            <li>
                                <strong>{{ status_label($action->action) }}</strong>
                                <span class="muted">— {{ $action->fromUser?->name ?? __('common.system') }}</span>
                                <div class="when">{{ fmt_datetime($action->created_at) }}</div>
                                @if($action->action === 'forwarded')
                                    <div class="small">{{ __('correspondence.forwarded_to') }}: {{ collect([$action->toUser?->name, $action->toDepartment?->displayName()])->filter()->join(' / ') }}</div>
                                @endif
                                @if($action->action === 'status_change')
                                    <div class="small">{{ status_label($action->from_status) }} &rarr; {{ status_label($action->to_status) }}</div>
                                @endif
                                @if($action->follow_up_date)<div class="small">{{ __('correspondence.follow_up_date') }}: {{ fmt_date($action->follow_up_date) }}</div>@endif
                                @if($action->note)<div class="small prose">{{ $action->note }}</div>@endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-attachments :model="$item" :categories="['scan', 'general', 'report']" />
        </div>
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$item->status" /></dd>
                    <dt>{{ __('correspondence.direction') }}</dt><dd><x-badge :value="$item->direction" /></dd>
                    <dt>{{ __('correspondence.external_reference') }}</dt><dd>{{ $item->external_reference ?? '—' }}</dd>
                    <dt>{{ __('correspondence.sender') }}</dt><dd>{{ $item->sender }}</dd>
                    <dt>{{ __('correspondence.recipient') }}</dt><dd>{{ $item->recipient }}</dd>
                    <dt>{{ __('correspondence.letter_date') }}</dt><dd>{{ fmt_date($item->letter_date) }}</dd>
                    <dt>{{ __('correspondence.received_or_sent_date') }}</dt><dd>{{ fmt_date($item->received_or_sent_date) }}</dd>
                    <dt>{{ __('correspondence.delivery_method') }}</dt><dd>{{ status_label($item->delivery_method) }}</dd>
                    <dt>{{ __('correspondence.responsible_department') }}</dt><dd>{{ $item->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('correspondence.assigned_officer') }}</dt><dd>{{ $item->assignee?->name ?? __('common.unassigned') }}</dd>
                    <dt>{{ __('correspondence.response_deadline') }}</dt><dd>{{ fmt_date($item->response_deadline) }}</dd>
                    <dt>{{ __('correspondence.priority') }}</dt><dd><x-badge :value="$item->priority" /></dd>
                    <dt>{{ __('correspondence.confidentiality') }}</dt><dd><x-badge :value="$item->confidentiality" /></dd>
                    <dt>{{ __('common.project') }}</dt><dd>{{ $item->project?->name ?? '—' }}</dd>
                    <dt>{{ __('correspondence.registered_by') }}</dt><dd>{{ $item->creator?->name ?? '—' }}</dd>
                    @if($item->closed_at)<dt>{{ __('correspondence.closed_at') }}</dt><dd>{{ fmt_datetime($item->closed_at) }}</dd>@endif
                </dl>
            </x-card>

            @if(($item->inReplyTo && $item->inReplyTo->canBeViewedBy(auth()->user())) || $replies->isNotEmpty())
                <x-card :title="__('correspondence.related')">
                    @if($item->inReplyTo && $item->inReplyTo->canBeViewedBy(auth()->user()))
                        <p class="small">{{ __('correspondence.in_reply_to') }}: <a href="{{ route('correspondence.show', $item->inReplyTo) }}">{{ $item->inReplyTo->reference }}</a> — {{ $item->inReplyTo->subject }}</p>
                    @endif
                    @foreach($replies as $reply)
                        <p class="small mb-1">{{ __('correspondence.reply') }}: <a href="{{ route('correspondence.show', $reply) }}">{{ $reply->reference }}</a> — {{ $reply->subject }}</p>
                    @endforeach
                </x-card>
            @endif

            <x-history :model="$item" />
        </div>
    </div>
</x-layout>
