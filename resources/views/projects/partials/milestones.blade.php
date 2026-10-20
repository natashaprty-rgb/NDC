<div class="stack">
    <div class="card">
        <div class="card__head"><h2>{{ __('Milestones, deliverables and deadlines') }}</h2>
            <span class="muted small">{{ __(':done of :total completed', ['done' => $project->completed_milestones_count, 'total' => $project->milestones_count]) }}</span></div>
        @if ($milestones->isEmpty())
            <x-empty icon="calendar" :message="__('No milestones have been defined yet.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Milestone') }}</th><th>{{ __('Deliverables') }}</th><th>{{ __('Deadline') }}</th>
                    <th>{{ __('Status') }}</th><th class="num">{{ __('Tasks') }}</th>@if ($canUpdate)<th></th>@endif
                </tr></thead>
                <tbody>
                @foreach ($milestones as $milestone)
                    <tr>
                        <td class="title-cell">{{ $milestone->title }}
                            @if ($milestone->description)<span class="sub prose">{{ $milestone->description }}</span>@endif</td>
                        <td class="small prose">{{ $milestone->deliverables ?: '—' }}</td>
                        <td class="nowrap">{{ fmt_date($milestone->due_date) }}
                            @if ($milestone->isOverdue())<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</td>
                        <td><x-status domain="milestone" :value="$milestone->status" />
                            @if ($milestone->completed_at)<span class="sub small muted">{{ fmt_date($milestone->completed_at) }}</span>@endif</td>
                        <td class="num">{{ $milestone->tasks_count }}</td>
                        @if ($canUpdate)
                            <td class="actions">
                                @if ($milestone->status !== 'completed')
                                    <form method="POST" action="{{ route('projects.milestones.complete', [$project, $milestone]) }}" class="inline-form">
                                        @csrf <button class="btn btn-sm btn-success">{{ __('Complete') }}</button>
                                    </form>
                                @endif
                                <a href="{{ route('projects.milestones.edit', [$project, $milestone]) }}" class="btn btn-sm">{{ __('Edit') }}</a>
                                <x-delete-button :action="route('projects.milestones.destroy', [$project, $milestone])" />
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    @if ($canUpdate)
        <form method="POST" action="{{ route('projects.milestones.store', $project) }}" class="card form">
            @csrf
            <div class="card__head"><h2>{{ __('Add milestone') }}</h2></div>
            <div class="card__body">
                @include('projects.milestones._fields', ['milestone' => new \App\Models\ProjectMilestone(['status' => 'pending'])])
            </div>
            <div class="card__foot form-actions"><button class="btn btn-primary"><x-icon name="plus" /> {{ __('Add milestone') }}</button></div>
        </form>
    @endif
</div>
