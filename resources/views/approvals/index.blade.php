<x-layouts.app :title="__('Approvals')">
    <x-page-header :title="__('Approvals')" :subtitle="__('Items waiting for your decision and the status of your own requests.')" />

    <div class="stack">
        <div class="card">
            <div class="card__head"><h2>{{ __('Awaiting your decision') }}</h2><span class="badge badge--accent">{{ $awaitingMe->count() }}</span></div>
            @if ($awaitingMe->isEmpty())
                <x-empty icon="stamp" :message="__('Nothing is waiting for your approval.')" />
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Item') }}</th><th>{{ __('Requested by') }}</th><th>{{ __('Requested') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($awaitingMe as $approval)
                        <tr>
                            <td class="title-cell"><a href="{{ $approval->approvable->approvalUrl() }}">{{ $approval->approvable->approvalTitle() }}</a>
                                @if ($approval->request_note)<span class="sub">{{ \Illuminate\Support\Str::limit($approval->request_note, 120) }}</span>@endif</td>
                            <td>{{ $approval->requester?->name }}</td>
                            <td class="nowrap">{{ fmt_date($approval->created_at, true) }}</td>
                            <td class="actions"><a class="btn btn-sm btn-primary" href="{{ $approval->approvable->approvalUrl() }}">{{ __('Review') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        @if ($tasksToReview->isNotEmpty())
            <div class="card">
                <div class="card__head"><h2>{{ __('Tasks awaiting review') }}</h2><span class="badge badge--accent">{{ $tasksToReview->count() }}</span></div>
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Task') }}</th><th>{{ __('Assigned to') }}</th><th>{{ __('Submitted') }}</th></tr></thead>
                    <tbody>
                    @foreach ($tasksToReview as $task)
                        <tr>
                            <td class="title-cell"><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                @if ($task->project)<span class="sub">{{ $task->project->code }}</span>@endif</td>
                            <td>{{ $task->assignee?->name ?? '—' }}</td>
                            <td class="nowrap">{{ fmt_date($task->submitted_at, true) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endif

        <div class="grid grid-2">
            <div class="card">
                <div class="card__head"><h2>{{ __('My pending requests') }}</h2></div>
                @if ($myRequests->isEmpty())
                    <x-empty :message="__('You have no pending requests.')" />
                @else
                    <ul class="list">
                        @foreach ($myRequests as $approval)
                            <li><a href="{{ $approval->approvable?->approvalUrl() }}">{{ $approval->approvable?->approvalTitle() }}</a>
                                <div class="list__meta">{{ fmt_date($approval->created_at, true) }}</div></li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="card">
                <div class="card__head"><h2>{{ __('Recently decided by me') }}</h2></div>
                @if ($recent->isEmpty())
                    <x-empty :message="__('No decisions yet.')" />
                @else
                    <ul class="list">
                        @foreach ($recent as $approval)
                            <li><div class="list__row"><a href="{{ $approval->approvable?->approvalUrl() }}">{{ $approval->approvable?->approvalTitle() }}</a>
                                <x-status domain="approval" :value="$approval->status" /></div>
                                <div class="list__meta">{{ fmt_date($approval->decided_at, true) }}</div></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
