<x-layout :title="__('dashboard.title')" :subtitle="__('dashboard.subtitle', ['date' => fmt_date(now())])">
    <div class="grid grid-4">
        <x-stat :label="__('dashboard.pending_approvals')" :value="$m['pending_approvals']" :href="route_if('approvals.index')" :tone="$m['pending_approvals'] > 0 ? 'warning' : null" :meta="__('dashboard.awaiting_your_decision')" />
        <x-stat :label="__('dashboard.my_open_tasks')" :value="$m['my_tasks']['open']" :href="route_if('tasks.index', ['scope' => 'mine'])" />
        <x-stat :label="__('dashboard.my_overdue_tasks')" :value="$m['my_tasks']['overdue']" :href="route_if('tasks.index', ['scope' => 'mine', 'overdue' => 1])" :tone="$m['my_tasks']['overdue'] > 0 ? 'danger' : 'success'" />
        @isset($m['projects'])
            <x-stat :label="__('dashboard.active_projects')" :value="$m['projects']['active']" :href="route_if('projects.index', ['status' => 'in_progress'])" :meta="__('dashboard.of_total', ['total' => $m['projects']['total']])" />
        @endisset
        @isset($m['tasks'])
            <x-stat :label="__('dashboard.tasks_pending')" :value="$m['tasks']['pending']" :href="route_if('tasks.index', ['scope' => 'all', 'status' => 'open'])" :meta="__('dashboard.tasks_completed', ['n' => $m['tasks']['completed']])" />
            <x-stat :label="__('dashboard.tasks_overdue')" :value="$m['tasks']['overdue']" :href="route_if('tasks.index', ['scope' => 'all', 'overdue' => 1])" :tone="$m['tasks']['overdue'] > 0 ? 'danger' : 'success'" />
        @endisset
        @isset($m['staff'])
            <x-stat :label="__('dashboard.registered_staff')" :value="$m['staff']['total']" :href="route_if('staff.index')" :meta="__('dashboard.departments_n', ['n' => $m['staff']['departments']])" />
        @endisset
        @isset($m['documents'])
            <x-stat :label="__('dashboard.documents')" :value="$m['documents']['total']" :href="route_if('documents.index')" :meta="__('dashboard.documents_meta', ['review' => $m['documents']['pending_review'], 'expiring' => $m['documents']['expiring']])" />
        @endisset
        @isset($m['correspondence'])
            <x-stat :label="__('dashboard.open_incoming')" :value="$m['correspondence']['open_incoming']" :href="route_if('correspondence.index', ['direction' => 'incoming', 'status' => 'open'])" :tone="$m['correspondence']['overdue'] > 0 ? 'danger' : null" :meta="__('dashboard.overdue_responses', ['n' => $m['correspondence']['overdue']])" />
        @endisset
        @isset($m['procurement'])
            <x-stat :label="__('dashboard.pending_purchase_requests')" :value="$m['procurement']['pending']" :href="route_if('purchase-requests.index', ['status' => 'submitted'])" :meta="__('dashboard.procurement_meta', ['approved' => $m['procurement']['approved_not_ordered'], 'delivery' => $m['procurement']['in_delivery']])" />
        @endisset
        @isset($m['assets'])
            <x-stat :label="__('dashboard.registered_assets')" :value="$m['assets']['total']" :href="route_if('assets.index')" :meta="__('dashboard.assets_in_use', ['n' => $m['assets']['by_status']['in_use'] ?? 0])" />
        @endisset
    </div>

    <div class="layout-main-side mt-2">
        <div>
            @isset($m['budget'])
                <x-card :title="__('dashboard.budget_title', ['year' => $m['budget']['year']])">
                    <x-slot:headerActions>@if(route_if('budgets.index'))<a href="{{ route('budgets.index', ['fiscal_year' => $m['budget']['year']]) }}" class="btn btn--sm">{{ __('common.view_all') }}</a>@endif</x-slot:headerActions>
                    @if($m['budget']['budgets'] === 0)
                        <x-empty :message="__('dashboard.no_approved_budgets')" icon="wallet" />
                    @else
                        <div class="grid grid-4">
                            <div><div class="stat__label">{{ __('dashboard.allocated') }}</div><strong>{{ money($m['budget']['allocated']) }}</strong></div>
                            <div><div class="stat__label">{{ __('dashboard.recorded_spend') }}</div><strong>{{ money($m['budget']['recorded']) }}</strong></div>
                            <div><div class="stat__label">{{ __('dashboard.approved_spend') }}</div><strong>{{ money($m['budget']['approved']) }}</strong></div>
                            <div><div class="stat__label">{{ __('dashboard.remaining') }}</div><strong>{{ money($m['budget']['remaining']) }}</strong></div>
                        </div>
                        <div class="mt-2"><x-progress :value="$m['budget']['percent']" /></div>
                        <p class="small muted mt-1 mb-0">{{ __('dashboard.budget_note') }}</p>
                    @endif
                </x-card>
            @endisset

            @isset($m['projects'])
                <x-card :title="__('dashboard.projects_by_status')">
                    @if($m['projects']['total'] === 0)
                        <x-empty :message="__('dashboard.no_projects')" icon="briefcase">
                            @can('projects.create')@if(route_if('projects.create'))<a href="{{ route('projects.create') }}" class="btn btn--primary btn--sm">{{ __('dashboard.create_project') }}</a>@endif @endcan
                        </x-empty>
                    @else
                        @php $max = max(1, max($m['projects']['by_status'])); @endphp
                        <ul class="barlist">
                            @foreach($m['projects']['by_status'] as $status => $count)
                                <li>
                                    <a href="{{ route_if('projects.index', ['status' => $status]) }}">{{ status_label($status) }}</a>
                                    <div class="bar"><span style="width: {{ round($count * 100 / $max) }}%"></span></div>
                                    <span class="val">{{ $count }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if($m['projects']['overdue'] > 0)
                            <p class="small mt-1 mb-0"><x-badge value="overdue" /> {{ __('dashboard.projects_overdue', ['n' => $m['projects']['overdue']]) }}</p>
                        @endif
                        @if($m['projects']['recent']->isNotEmpty())
                            <div class="table-wrap mt-2"><table class="table">
                                <thead><tr><th>{{ __('common.name') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.progress') }}</th></tr></thead>
                                <tbody>
                                @foreach($m['projects']['recent'] as $project)
                                    <tr>
                                        <td><a href="{{ route_if('projects.show', $project) }}">{{ $project->name }}</a><br><span class="small muted">{{ $project->reference }}</span></td>
                                        <td><x-badge :value="$project->status" /></td>
                                        <td style="min-width:140px"><x-progress :value="$project->progressPercent()" /></td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table></div>
                        @endif
                    @endif
                </x-card>
            @endisset

            <x-card :title="__('dashboard.recent_activity')" :flush="true">
                @if($m['activity']->isEmpty())
                    <x-empty :message="__('dashboard.no_activity')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <tbody>
                        @foreach($m['activity'] as $log)
                            <tr>
                                <td class="nowrap small muted">{{ fmt_datetime($log->created_at) }}</td>
                                <td>{{ $log->user?->name ?? __('common.system') }}</td>
                                <td><x-badge :value="$log->event" /></td>
                                <td class="small">{{ $log->auditable_type ? __('dashboard.entity.'.$log->auditable_type) : '' }} {{ $log->auditable_id ? '#'.$log->auditable_id : '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-card>
        </div>

        <div>
            <x-card :title="__('dashboard.my_deadlines')" :flush="true">
                @if($m['my_tasks']['due_soon']->isEmpty())
                    <x-empty :message="__('dashboard.no_deadlines')" icon="clock" />
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach($m['my_tasks']['due_soon'] as $task)
                            <tr>
                                <td><a href="{{ route_if('tasks.show', $task) }}">{{ $task->title }}</a><br><span class="small muted">{{ $task->project?->name }}</span></td>
                                <td class="nowrap small">{{ fmt_date($task->due_date) }}</td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-card>

            <x-card :title="__('dashboard.upcoming_meetings')" :flush="true">
                <x-slot:headerActions>@if(route_if('meetings.calendar'))<a href="{{ route('meetings.calendar') }}" class="btn btn--sm">{{ __('dashboard.calendar') }}</a>@endif</x-slot:headerActions>
                @if($m['meetings']->isEmpty())
                    <x-empty :message="__('dashboard.no_meetings')" icon="calendar" />
                @else
                    <div class="table-wrap"><table class="table"><tbody>
                        @foreach($m['meetings'] as $meeting)
                            <tr>
                                <td><a href="{{ route_if('meetings.show', $meeting) }}">{{ $meeting->title }}</a><br><span class="small muted">{{ $meeting->location }}</span></td>
                                <td class="nowrap small">{{ fmt_datetime($meeting->starts_at) }}</td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-card>

            @isset($m['assets'])
                <x-card :title="__('dashboard.assets_by_status')">
                    @if($m['assets']['total'] === 0)
                        <x-empty :message="__('dashboard.no_assets')" icon="box" />
                    @else
                        @php $max = max(1, max($m['assets']['by_status'])); @endphp
                        <ul class="barlist">
                            @foreach($m['assets']['by_status'] as $status => $count)
                                <li><a href="{{ route_if('assets.index', ['status' => $status]) }}">{{ status_label($status) }}</a><div class="bar"><span style="width: {{ round($count * 100 / $max) }}%"></span></div><span class="val">{{ $count }}</span></li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endisset
        </div>
    </div>
</x-layout>
