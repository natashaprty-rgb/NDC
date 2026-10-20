<x-layouts.app :title="__('Dashboard')">
    <x-page-header :title="__('Welcome, :name', ['name' => $user->name])" :subtitle="fmt_date(now()).' · '.__('All figures are calculated from current system records.')">
        <x-slot:actions>
            @if ($pendingApprovals > 0)
                <a href="{{ route('approvals.index') }}" class="btn btn-accent"><x-icon name="stamp" /> {{ trans_choice(':count item awaiting your approval|:count items awaiting your approval', $pendingApprovals) }}</a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @if ($tiles)
        <div class="grid grid-4" style="margin-bottom:1rem">
            @foreach ($tiles as $tile)
                <div @class(['card', 'stat', 'stat--alert' => false])>
                    <span class="stat__label">{{ $tile['label'] }}</span>
                    <span class="stat__value">{{ number_format($tile['value']) }}</span>
                    <span class="stat__meta">
                        @if ($tile['alert'])<x-icon name="alert" style="color:var(--danger)" />@endif
                        {{ $tile['meta'] }}
                        @if ($tile['url']) · <a href="{{ $tile['url'] }}">{{ __('View') }}</a>@endif
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="grid grid-main">
        <div class="stack">
            @if ($projectStatus !== null)
                <div class="card">
                    <div class="card__head"><h2>{{ __('Projects') }}</h2>
                        @if (Route::has('projects.index'))<a href="{{ route('projects.index') }}" class="small">{{ __('All projects') }}</a>@endif</div>
                    <div class="card__body">
                        @php $max = max(1, (int) $projectStatus->max()); @endphp
                        @if ($projectStatus->sum() === 0)
                            <p class="muted">{{ __('No projects have been registered yet.') }}</p>
                        @else
                            <table class="table" aria-label="{{ __('Projects by status') }}">
                                <tbody>
                                @foreach (\App\Support\Status::all('project') as $status => $def)
                                    @php $count = (int) ($projectStatus[$status] ?? 0); @endphp
                                    <tr>
                                        <td style="width:30%"><x-status domain="project" :value="$status" /></td>
                                        <td><div class="progress" style="height:10px" title="{{ $count }}"><div class="progress__bar" style="width: {{ round($count / $max * 100) }}%"></div></div></td>
                                        <td class="num" style="width:4rem"><strong>{{ $count }}</strong></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                    @if ($activeProjects->isNotEmpty())
                        <div class="table-wrap"><table class="table">
                            <thead><tr><th>{{ __('Active project') }}</th><th>{{ __('Due') }}</th><th style="min-width:140px">{{ __('Progress') }}</th></tr></thead>
                            <tbody>
                            @foreach ($activeProjects as $project)
                                <tr>
                                    <td class="title-cell"><a href="{{ route('projects.show', $project) }}">{{ $project->title }}</a>
                                        <span class="sub">{{ $project->code }} · {{ $project->manager?->name ?? __('No manager') }}</span></td>
                                    <td class="nowrap">{{ fmt_date($project->expected_end_date) }}
                                        @if ($project->isDelayed())<span class="badge badge--danger">{{ __('Delayed') }}</span>@endif</td>
                                    <td><x-progress :value="$project->progress" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @endif
                </div>
            @endif

            @can('tasks.view')
                <div class="card">
                    <div class="card__head"><h2>{{ __('Overdue tasks') }}</h2>
                        @if (Route::has('tasks.index'))<a href="{{ route('tasks.index', ['overdue' => 1]) }}" class="small">{{ __('View all') }}</a>@endif</div>
                    @if ($overdueTasks->isEmpty())
                        <x-empty icon="check" :message="__('No overdue tasks.')" />
                    @else
                        <div class="table-wrap"><table class="table">
                            <thead><tr><th>{{ __('Task') }}</th><th>{{ __('Assigned to') }}</th><th>{{ __('Due') }}</th></tr></thead>
                            <tbody>
                            @foreach ($overdueTasks as $task)
                                <tr>
                                    <td class="title-cell"><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                        @if ($task->project)<span class="sub">{{ $task->project->code }}</span>@endif</td>
                                    <td>{{ $task->assignee?->name ?? '—' }}</td>
                                    <td class="nowrap"><span class="badge badge--danger">{{ fmt_date($task->due_date) }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @endif
                </div>
            @endcan

            @if ($budget)
                <div class="card">
                    <div class="card__head"><h2>{{ __('Budget overview') }}</h2>
                        @if (Route::has('budgets.index'))<a href="{{ route('budgets.index') }}" class="small">{{ __('Budgets') }}</a>@endif</div>
                    <div class="card__body">
                        @if ($budget['count'] === 0)
                            <p class="muted">{{ __('No approved budgets yet.') }}</p>
                        @else
                            <div class="grid grid-3">
                                <div><div class="stat__label">{{ __('Approved budgets (:n)', ['n' => $budget['count']]) }}</div><div class="stat__value" style="font-size:1.25rem">{{ money($budget['amount']) }}</div></div>
                                <div><div class="stat__label">{{ __('Approved expenditure') }}</div><div class="stat__value" style="font-size:1.25rem">{{ money($budget['spent']) }}</div></div>
                                <div><div class="stat__label">{{ __('Remaining') }}</div><div class="stat__value" style="font-size:1.25rem">{{ money($budget['remaining']) }}</div></div>
                            </div>
                            <div style="margin-top:1rem"><x-progress :value="$budget['utilisation']" :tone="$budget['utilisation'] > 100 ? 'danger' : ($budget['utilisation'] >= 80 ? 'warn' : null)" /></div>
                            @if ($budget['pending'] > 0)
                                <p class="small muted" style="margin-top:.5rem">{{ __(':amount in expenses awaiting approval.', ['amount' => money($budget['pending'])]) }}</p>
                            @endif
                            <p class="small muted">{{ __('Amounts are summed across currencies as recorded; review per-budget pages for multi-currency budgets.') }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Upcoming (next 14 days)') }}</h2>
                    @if (Route::has('meetings.calendar'))<a href="{{ route('meetings.calendar') }}" class="small">{{ __('Calendar') }}</a>@endif</div>
                @if ($agenda->isEmpty())
                    <x-empty icon="calendar" :message="__('Nothing scheduled.')" />
                @else
                    <ul class="list">
                        @foreach ($agenda as $item)
                            <li>
                                <div class="list__row">
                                    <div>
                                        @if ($item['url'])<a href="{{ $item['url'] }}">{{ $item['title'] }}</a>@else{{ $item['title'] }}@endif
                                        <div class="list__meta">{{ $item['type'] }}</div>
                                    </div>
                                    <span @class(['badge', 'badge--plain', 'badge--danger' => $item['overdue'] ?? false, 'badge--info' => ! ($item['overdue'] ?? false)])>{{ fmt_date($item['date'], $item['time'] ?? false) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @can('tasks.view')
                <div class="card">
                    <div class="card__head"><h2>{{ __('My tasks') }}</h2></div>
                    @if ($myTasks->isEmpty())
                        <x-empty icon="check" :message="__('You have no open tasks.')" />
                    @else
                        <ul class="list">
                            @foreach ($myTasks as $task)
                                <li><div class="list__row">
                                    <a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                    <x-status domain="task" :value="$task->status" />
                                </div>
                                <div class="list__meta">{{ __('Due') }}: {{ fmt_date($task->due_date) }}</div></li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endcan

            <div class="card">
                <div class="card__head"><h2>{{ __('Notifications') }}</h2><a href="{{ route('notifications.index') }}" class="small">{{ __('View all') }}</a></div>
                @if ($notifications->isEmpty())
                    <x-empty icon="bell" :message="__('No unread notifications.')" />
                @else
                    <ul class="list">
                        @foreach ($notifications as $notification)
                            <li class="is-unread"><a href="{{ route('notifications.open', $notification->id) }}">{{ $notification->data['title'] ?? '' }}</a>
                                <div class="list__meta">{{ \Illuminate\Support\Str::limit($notification->data['body'] ?? '', 90) }}</div></li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="card">
                <div class="card__head"><h2>{{ auth()->user()->can('audit.view') ? __('Recent system activity') : __('My recent activity') }}</h2></div>
                @if ($activity->isEmpty())
                    <x-empty icon="history" :message="__('No activity recorded.')" />
                @else
                    <ul class="list">
                        @foreach ($activity as $log)
                            <li><div>{{ $log->user?->name ?? __('System') }} · <span class="mono small">{{ $log->event }}</span> {{ \Illuminate\Support\Str::limit($log->description, 50) }}</div>
                                <div class="list__meta">{{ fmt_date($log->created_at, true) }}</div></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
