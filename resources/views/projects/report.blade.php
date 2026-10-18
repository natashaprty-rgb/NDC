<x-layout :title="__('projects.report').': '.$project->name" :subtitle="$project->reference"
          :breadcrumbs="[__('projects.title') => route('projects.index'), $project->name => route('projects.show', $project), __('projects.report')]">
    <div class="alert alert--info no-print">
        {{ __('projects.print_hint') }}
        <a href="{{ route('projects.show', $project) }}" class="btn btn--sm" style="margin-left:.5rem">{{ __('projects.back_to_project') }}</a>
    </div>

    <p class="small muted">{{ __('projects.report_generated', ['date' => fmt_datetime(now()), 'user' => auth()->user()->name]) }}</p>

    <x-card :title="__('projects.overview')">
        <dl class="dl">
            <dt>{{ __('common.status') }}</dt><dd>{{ status_label($project->status) }}</dd>
            <dt>{{ __('projects.priority') }}</dt><dd>{{ status_label($project->priority) }}</dd>
            <dt>{{ __('projects.manager') }}</dt><dd>{{ $project->manager?->name ?? '—' }}</dd>
            <dt>{{ __('common.department') }}</dt><dd>{{ $project->department?->displayName() ?? '—' }}</dd>
            <dt>{{ __('projects.start_date') }}</dt><dd>{{ fmt_date($project->start_date) }}</dd>
            <dt>{{ __('projects.expected_end_date') }}</dt><dd>{{ fmt_date($project->expected_end_date) }}</dd>
            <dt>{{ __('projects.actual_end_date') }}</dt><dd>{{ fmt_date($project->actual_end_date) }}</dd>
            <dt>{{ __('common.progress') }}</dt><dd><x-progress :value="$progress" /></dd>
        </dl>
        @if($project->objectives)<h3 class="mt-2">{{ __('projects.objectives') }}</h3><p class="prose mb-0">{{ $project->objectives }}</p>@endif
    </x-card>

    <x-card :title="__('projects.task_summary')">
        <dl class="dl">
            @foreach(\App\Models\Task::STATUSES as $status)
                <dt>{{ status_label($status) }}</dt><dd>{{ $taskStats->get($status, 0) }}</dd>
            @endforeach
            <dt>{{ __('projects.overdue_tasks') }}</dt><dd>{{ $overdueTasks }}</dd>
        </dl>
    </x-card>

    <x-card :title="__('projects.milestones')" :flush="true">
        @if($project->milestones->isEmpty())
            <x-empty :message="__('projects.no_milestones')" />
        @else
            <table class="table"><thead><tr><th>{{ __('common.title') }}</th><th>{{ __('projects.due_date') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                <tbody>@foreach($project->milestones as $m)<tr><td>{{ $m->title }}</td><td>{{ fmt_date($m->due_date) }}</td><td>{{ status_label($m->status) }}</td></tr>@endforeach</tbody></table>
        @endif
    </x-card>

    <x-card :title="__('projects.risks')" :flush="true">
        @if($project->risks->isEmpty())
            <x-empty :message="__('projects.no_risks')" />
        @else
            <table class="table"><thead><tr><th>{{ __('common.title') }}</th><th>{{ __('common.type') }}</th><th>{{ __('projects.severity') }}</th><th>{{ __('projects.risk_owner') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                <tbody>@foreach($project->risks as $r)<tr><td>{{ $r->title }}@if($r->corrective_action)<div class="small muted">{{ $r->corrective_action }}</div>@endif</td><td>{{ status_label($r->type) }}</td><td>{{ $r->severity() }} / 9</td><td>{{ $r->owner?->name ?? '—' }}</td><td>{{ status_label($r->status) }}</td></tr>@endforeach</tbody></table>
        @endif
    </x-card>

    @if($budgets->isNotEmpty())
        <x-card :title="__('projects.budget')" :flush="true">
            <table class="table"><thead><tr><th>{{ __('common.title') }}</th><th class="num">{{ __('projects.allocated') }}</th><th class="num">{{ __('projects.recorded') }}</th><th class="num">{{ __('projects.approved') }}</th><th class="num">{{ __('projects.remaining') }}</th></tr></thead>
                <tbody>@foreach($budgets as $row)<tr><td>{{ $row['budget']->title }} ({{ $row['budget']->fiscal_year }})</td><td class="num">{{ money($row['summary']['allocated'], $row['budget']->currency) }}</td><td class="num">{{ money($row['summary']['recorded'], $row['budget']->currency) }}</td><td class="num">{{ money($row['summary']['approved'], $row['budget']->currency) }}</td><td class="num">{{ money($row['summary']['remaining'], $row['budget']->currency) }}</td></tr>@endforeach</tbody></table>
        </x-card>
    @endif

    @if($project->stakeholders->isNotEmpty())
        <x-card :title="__('projects.stakeholders')" :flush="true">
            <table class="table"><tbody>@foreach($project->stakeholders as $s)<tr><td>{{ $s->name }}</td><td>{{ status_label($s->type) }}</td><td>{{ $s->organization }}</td><td>{{ $s->role_description }}</td></tr>@endforeach</tbody></table>
        </x-card>
    @endif
</x-layout>
