<div class="card">
    <div class="card__head">
        <h2>{{ __('Project tasks') }}</h2>
        <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
            @if (Route::has('tasks.index'))
                <a href="{{ route('tasks.index', ['project' => $project->id]) }}" class="small">{{ __('Open in task list') }}</a>
            @endif
            @if (! $project->isArchived() && Route::has('tasks.create'))
                @can('create', \App\Models\Task::class)
                    <a href="{{ route('tasks.create', ['project_id' => $project->id]) }}" class="btn btn-sm btn-primary"><x-icon name="plus" /> {{ __('New task') }}</a>
                @endcan
            @endif
        </div>
    </div>
    @if ($taskCompletion !== null)
        <div class="card__body">
            <div class="label">{{ __('Task completion') }}</div>
            <x-progress :value="$taskCompletion" />
        </div>
    @endif
    @if ($tasks->isEmpty())
        <x-empty icon="check" :message="__('No tasks that you can see are linked to this project.')" />
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr>
                <th>{{ __('Task') }}</th><th>{{ __('Assigned to') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th>
                <th style="min-width:120px">{{ __('Progress') }}</th><th>{{ __('Due date') }}</th>
            </tr></thead>
            <tbody>
            @foreach ($tasks as $task)
                <tr>
                    <td class="title-cell"><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                        @if ($task->milestone)<span class="sub">{{ $task->milestone->title }}</span>@endif</td>
                    <td>{{ $task->assignee?->name ?? '—' }}@if ($task->department)<span class="sub small muted">{{ $task->department->displayName() }}</span>@endif</td>
                    <td><x-status domain="priority" :value="$task->priority" /></td>
                    <td><x-status domain="task" :value="$task->status" />
                        @if ($task->isOverdue())<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</td>
                    <td><x-progress :value="$task->progress" /></td>
                    <td class="nowrap">{{ fmt_date($task->due_date) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
    {{ $tasks->links() }}
</div>
