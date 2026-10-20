@php
    $planned = (float) $project->planned_budget;
@endphp
<x-layouts.app :title="__('Project progress report')">
    <x-page-header :title="__('Project progress report')" :subtitle="$project->code.' — '.$project->title"
        :breadcrumbs="[__('Projects') => route('projects.index'), $project->code => route('projects.show', $project)]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
            <a href="{{ route('projects.show', $project) }}" class="btn btn-ghost">{{ __('Back to project') }}</a>
        </x-slot:actions>
    </x-page-header>

    <p class="small muted">{{ setting('organisation_name', __('National Data Center Somalia')) }} · {{ __('Generated on :date by :name', ['date' => fmt_date(now(), true), 'name' => auth()->user()->name]) }}</p>

    <div class="stack">
        <div class="card">
            <div class="card__head"><h2>{{ __('Summary') }}</h2>
                <div><x-status domain="project" :value="$project->status" />
                    @if ($project->isDelayed())<span class="badge badge--danger">{{ __('Delayed') }}</span>@endif
                    @if ($project->isArchived())<span class="badge badge--neutral">{{ __('Archived') }}</span>@endif</div></div>
            <div class="card__body grid grid-2">
                <dl class="dl">
                    <dt>{{ __('Responsible department') }}</dt><dd>{{ $project->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('Project manager') }}</dt><dd>{{ $project->manager?->name ?? '—' }}</dd>
                    <dt>{{ __('Location') }}</dt><dd>{{ $project->location ?: '—' }}</dd>
                    <dt>{{ __('Priority') }}</dt><dd>{{ \App\Support\Status::label('priority', $project->priority) }}</dd>
                    <dt>{{ __('Start date') }}</dt><dd>{{ fmt_date($project->start_date) }}</dd>
                    <dt>{{ __('Expected completion date') }}</dt><dd>{{ fmt_date($project->expected_end_date) }}</dd>
                    <dt>{{ __('Actual completion date') }}</dt><dd>{{ fmt_date($project->actual_end_date) }}</dd>
                </dl>
                <dl class="dl">
                    <dt>{{ __('Reported implementation progress') }}</dt><dd><x-progress :value="$project->progress" /></dd>
                    <dt>{{ __('Task completion') }}</dt><dd>@if ($taskCompletion === null) — @else<x-progress :value="$taskCompletion" />@endif</dd>
                    <dt>{{ __('Milestones completed') }}</dt><dd>{{ __(':done of :total', ['done' => $project->milestones->where('status', 'completed')->count(), 'total' => $project->milestones->count()]) }}</dd>
                    <dt>{{ __('Overdue tasks') }}</dt><dd>{{ $overdueTasks }}</dd>
                    <dt>{{ __('Planned budget') }}</dt><dd>{{ money($project->planned_budget, $project->currency) }}</dd>
                    <dt>{{ __('Recorded expenditure') }}</dt><dd>{{ money($expenditure, $project->currency) }}</dd>
                    <dt>{{ __('Remaining') }}</dt><dd>{{ money($planned - $expenditure, $project->currency) }}</dd>
                </dl>
            </div>
        </div>

        @if ($project->description || $project->objectives)
            <div class="card">
                <div class="card__head"><h2>{{ __('Description and objectives') }}</h2></div>
                <div class="card__body">
                    @if ($project->description)<p class="prose">{{ $project->description }}</p>@endif
                    @if ($project->objectives)<h3>{{ __('Objectives') }}</h3><p class="prose mb-0">{{ $project->objectives }}</p>@endif
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card__head"><h2>{{ __('Tasks by status') }}</h2></div>
            @if ($taskCounts->isEmpty())
                <x-empty icon="check" :message="__('No tasks have been created for this project yet.')" />
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Status') }}</th><th class="num">{{ __('Tasks') }}</th></tr></thead>
                    <tbody>
                    @foreach (\App\Support\Status::options('task') as $status => $label)
                        @if ($taskCounts->has($status))
                            <tr><td>{{ $label }}</td><td class="num">{{ $taskCounts[$status] }}</td></tr>
                        @endif
                    @endforeach
                    <tr><td><strong>{{ __('Total') }}</strong></td><td class="num"><strong>{{ $taskCounts->sum() }}</strong></td></tr>
                    </tbody>
                </table></div>
            @endif
        </div>

        <div class="card">
            <div class="card__head"><h2>{{ __('Milestones') }}</h2></div>
            @if ($project->milestones->isEmpty())
                <x-empty icon="calendar" :message="__('No milestones have been defined yet.')" />
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Milestone') }}</th><th>{{ __('Deliverables') }}</th><th>{{ __('Deadline') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @foreach ($project->milestones as $milestone)
                        <tr>
                            <td>{{ $milestone->title }}</td>
                            <td class="small prose">{{ $milestone->deliverables ?: '—' }}</td>
                            <td class="nowrap">{{ fmt_date($milestone->due_date) }}@if ($milestone->isOverdue()) <span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</td>
                            <td>{{ \App\Support\Status::label('milestone', $milestone->status) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        <div class="card">
            <div class="card__head"><h2>{{ __('Risks, challenges and delays') }}</h2></div>
            @if ($project->risks->isEmpty())
                <x-empty icon="alert" :message="__('No risks, challenges or delays have been recorded.')" />
            @else
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('Type') }}</th><th>{{ __('Title') }}</th><th>{{ __('Likelihood') }} / {{ __('Impact') }}</th><th>{{ __('Corrective action') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @foreach ($project->risks as $risk)
                        <tr>
                            <td>{{ \App\Support\Status::label('risk_type', $risk->type) }}</td>
                            <td>{{ $risk->title }}@if ($risk->owner)<span class="sub small muted">{{ $risk->owner->name }}</span>@endif</td>
                            <td>{{ \App\Support\Status::label('level', $risk->likelihood) }} / {{ \App\Support\Status::label('level', $risk->impact) }}</td>
                            <td class="small prose">{{ $risk->corrective_action ?: '—' }}</td>
                            <td>{{ \App\Support\Status::label('risk', $risk->status) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        <div class="grid grid-2">
            <div class="card">
                <div class="card__head"><h2>{{ __('Stakeholders') }}</h2></div>
                @if ($project->stakeholders->isEmpty())
                    <x-empty icon="users" :message="__('No stakeholders have been recorded.')" />
                @else
                    <ul class="list">
                        @foreach ($project->stakeholders as $stakeholder)
                            <li>{{ $stakeholder->name }} <span class="muted small">· {{ \App\Support\Status::label('stakeholder_type', $stakeholder->type) }}@if ($stakeholder->organization) · {{ $stakeholder->organization }}@endif</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="card">
                <div class="card__head"><h2>{{ __('Project team') }}</h2></div>
                @if ($project->members->isEmpty())
                    <x-empty icon="users" :message="__('No team members have been added.')" />
                @else
                    <ul class="list">
                        @foreach ($project->members->sortBy('name') as $member)
                            <li>{{ $member->name }}@if ($member->pivot->role) <span class="muted small">· {{ $member->pivot->role }}</span>@endif</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
