@php
    $planned = (float) $project->planned_budget;
    $utilisation = $planned > 0 ? (int) round($expenditure * 100 / $planned) : null;
@endphp
<div class="grid grid-main">
    <div class="stack">
        <div class="card">
            <div class="card__head"><h2>{{ __('Project details') }}</h2></div>
            <div class="card__body">
                <dl class="dl">
                    <dt>{{ __('Project code') }}</dt><dd class="mono">{{ $project->code }}</dd>
                    <dt>{{ __('Status') }}</dt><dd><x-status domain="project" :value="$project->status" /></dd>
                    <dt>{{ __('Priority') }}</dt><dd><x-status domain="priority" :value="$project->priority" /></dd>
                    <dt>{{ __('Responsible department') }}</dt><dd>{{ $project->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('Project manager') }}</dt><dd>{{ $project->manager?->name ?? '—' }}</dd>
                    <dt>{{ __('Location') }}</dt><dd>{{ $project->location ?: '—' }}</dd>
                    <dt>{{ __('Start date') }}</dt><dd>{{ fmt_date($project->start_date) }}</dd>
                    <dt>{{ __('Expected completion date') }}</dt>
                    <dd>{{ fmt_date($project->expected_end_date) }} @if ($project->isDelayed())<span class="badge badge--danger">{{ __('Delayed') }}</span>@endif</dd>
                    <dt>{{ __('Actual completion date') }}</dt><dd>{{ fmt_date($project->actual_end_date) }}</dd>
                    <dt>{{ __('Created by') }}</dt><dd>{{ $project->creator?->name ?? '—' }} · {{ fmt_date($project->created_at, true) }}</dd>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card__head"><h2>{{ __('Description') }}</h2></div>
            <div class="card__body prose">{{ $project->description ?: __('No description recorded.') }}</div>
        </div>
        <div class="card">
            <div class="card__head"><h2>{{ __('Objectives') }}</h2></div>
            <div class="card__body prose">{{ $project->objectives ?: __('No objectives recorded.') }}</div>
        </div>
    </div>
    <div class="stack">
        <div class="card">
            <div class="card__head"><h2>{{ __('Progress') }}</h2></div>
            <div class="card__body">
                <div class="label">{{ __('Reported implementation progress') }}</div>
                <x-progress :value="$project->progress" :tone="$project->isDelayed() ? 'danger' : null" />
                <div class="label" style="margin-top:1rem">{{ __('Task completion') }}</div>
                @if ($taskCompletion === null)
                    <p class="muted small mb-0">{{ __('No tasks have been created for this project yet.') }}</p>
                @else
                    <x-progress :value="$taskCompletion" />
                    <p class="help mb-0">{{ __('Share of the project\'s tasks that are completed (cancelled tasks excluded). For information only; implementation progress is reported manually.') }}</p>
                @endif
                <dl class="dl" style="margin-top:1rem">
                    <dt>{{ __('Milestones completed') }}</dt><dd>{{ __(':done of :total', ['done' => $project->completed_milestones_count, 'total' => $project->milestones_count]) }}</dd>
                    <dt>{{ __('Open risks and issues') }}</dt><dd>{{ $project->open_risks_count }}</dd>
                </dl>
            </div>
        </div>
        <div class="card">
            <div class="card__head"><h2>{{ __('Budget') }}</h2>
                @if (Route::has('budgets.index'))
                    @can('budgets.view')<a href="{{ route('budgets.index', ['project' => $project->id]) }}" class="small">{{ __('Budgets & Expenses') }}</a>@endcan
                @endif
            </div>
            <div class="card__body">
                <dl class="dl">
                    <dt>{{ __('Planned budget') }}</dt><dd class="num">{{ money($project->planned_budget, $project->currency) }}</dd>
                    <dt>{{ __('Recorded expenditure') }}</dt><dd class="num">{{ money($expenditure, $project->currency) }}</dd>
                    <dt>{{ __('Remaining') }}</dt><dd class="num">{{ money($planned - $expenditure, $project->currency) }}</dd>
                </dl>
                @if ($utilisation !== null)
                    <div class="label" style="margin-top:1rem">{{ __('Budget utilisation') }}</div>
                    <x-progress :value="$utilisation" :tone="$utilisation > 100 ? 'danger' : ($utilisation >= 90 ? 'warn' : null)" />
                    @if ($utilisation > 100)<p class="field-error mb-0">{{ __('Recorded expenditure exceeds the planned budget.') }}</p>@endif
                @endif
                <p class="help mb-0">{{ __('Expenditure is the total of approved expenses recorded against this project\'s budgets.') }}</p>
            </div>
        </div>
    </div>
</div>
