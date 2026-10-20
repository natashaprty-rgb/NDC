@php
    $isIncoming = $letter->direction === 'incoming';
    $actionLabels = [
        'registered' => __('Registered'),
        'assigned' => __('Assigned'),
        'forwarded' => __('Forwarded'),
        'responded' => __('Response recorded'),
        'sent' => __('Sent'),
        'closed' => __('Closed'),
        'note' => __('Follow-up note'),
    ];
@endphp
<x-layouts.app :title="$letter->reference_number">
    <x-page-header :title="$letter->subject" :breadcrumbs="[__('Correspondence') => route('correspondence.index')]">
        <x-slot:meta>
            <p>
                <span class="mono">{{ $letter->reference_number }}</span>
                <x-status domain="correspondence_direction" :value="$letter->direction" />
                <x-status domain="correspondence" :value="$letter->status" />
                @if ($letter->is_confidential)<span class="badge badge--danger"><x-icon name="lock" /> {{ __('Confidential') }}</span>@endif
                @if ($letter->isOverdue())<span class="badge badge--danger">{{ __('Response overdue') }}</span>@endif
            </p>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $letter)
                <a href="{{ route('correspondence.edit', $letter) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endcan
            @can('delete', $letter)
                <x-delete-button :action="route('correspondence.destroy', $letter)" :label="__('Delete')" class="btn btn-danger" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Letter details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Reference number') }}</dt><dd class="mono">{{ $letter->reference_number }}</dd>
                        <dt>{{ __('Sender\'s reference number') }}</dt><dd>{{ $letter->external_reference ?: '—' }}</dd>
                        <dt>{{ __('Sender') }}</dt><dd>{{ $letter->sender }}</dd>
                        <dt>{{ __('Recipient') }}</dt><dd>{{ $letter->recipient }}</dd>
                        <dt>{{ __('Letter date') }}</dt><dd>{{ fmt_date($letter->letter_date) }}</dd>
                        <dt>{{ $isIncoming ? __('Date received') : __('Date sent') }}</dt><dd>{{ fmt_date($letter->received_or_sent_date) }}</dd>
                        <dt>{{ __('Department') }}</dt><dd>{{ $letter->department?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Responsible officer') }}</dt><dd>{{ $letter->responsibleOfficer?->name ?? '—' }}</dd>
                        <dt>{{ __('Assigned to') }}</dt><dd>{{ $letter->assignee?->name ?? '—' }}</dd>
                        <dt>{{ __('Response due') }}</dt>
                        <dd>{{ fmt_date($letter->response_due_date) }} @if ($letter->isOverdue())<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</dd>
                        <dt>{{ __('Priority') }}</dt><dd><x-status domain="correspondence_priority" :value="$letter->priority" /></dd>
                        @if ($letter->responded_at)<dt>{{ __('Responded') }}</dt><dd>{{ fmt_date($letter->responded_at, true) }}</dd>@endif
                        @if ($letter->closed_at)<dt>{{ __('Closed') }}</dt><dd>{{ fmt_date($letter->closed_at, true) }}</dd>@endif
                        <dt>{{ __('Registered by') }}</dt><dd>{{ $letter->creator?->name ?? '—' }} · {{ fmt_date($letter->created_at, true) }}</dd>
                        @if ($letter->parent)
                            <dt>{{ __('In reply to') }}</dt>
                            <dd>@if ($parentVisible)<a href="{{ route('correspondence.show', $letter->parent) }}" class="mono">{{ $letter->parent->reference_number }}</a> — {{ $letter->parent->subject }}@else<span class="muted">{{ __('Restricted letter') }}</span>@endif</dd>
                        @endif
                    </dl>
                    @if ($letter->summary)
                        <h3>{{ __('Summary') }}</h3>
                        <div class="prose">{!! nl2br(e($letter->summary)) !!}</div>
                    @endif
                    @if ($letter->notes)
                        <h3>{{ __('Notes') }}</h3>
                        <div class="prose">{!! nl2br(e($letter->notes)) !!}</div>
                    @endif
                </div>
            </div>

            @if ($replies->isNotEmpty())
                <div class="card">
                    <div class="card__head"><h2>{{ __('Replies') }}</h2></div>
                    <ul class="list">
                        @foreach ($replies as $reply)
                            <li><div class="list__row">
                                <div>
                                    <a href="{{ route('correspondence.show', $reply) }}" class="mono">{{ $reply->reference_number }}</a> — {{ $reply->subject }}
                                    <div class="list__meta">{{ __('To: :name', ['name' => $reply->recipient]) }} · {{ fmt_date($reply->received_or_sent_date ?? $reply->letter_date) }}</div>
                                </div>
                                <x-status domain="correspondence" :value="$reply->status" />
                            </div></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="card" id="follow-up">
                <div class="card__head"><h2>{{ __('Follow-up history') }}</h2></div>
                <div class="card__body">
                    @if ($letter->actions->isEmpty())
                        <p class="muted">{{ __('No follow-up actions recorded yet.') }}</p>
                    @else
                        <ul class="timeline">
                            @foreach ($letter->actions as $action)
                                <li>
                                    <div>
                                        <strong>{{ $actionLabels[$action->action] ?? ucfirst($action->action) }}</strong>
                                        @if ($action->toUser) → {{ $action->toUser->name }}@endif
                                        <span class="muted small">· {{ $action->user?->name ?? __('System') }} · {{ fmt_date($action->created_at, true) }}</span>
                                    </div>
                                    @if ($action->due_date)<div class="small">{{ __('Response due: :date', ['date' => fmt_date($action->due_date)]) }}</div>@endif
                                    @if ($action->note)<div class="small prose">{!! nl2br(e($action->note)) !!}</div>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <x-attachments :model="$letter" :can-upload="auth()->user()->can('update', $letter)" :title="__('Scanned letter and attachments')" />
            <x-audit-trail :model="$letter" />
        </div>

        <div class="stack">
            @include('correspondence.partials.actions')
        </div>
    </div>
</x-layouts.app>
