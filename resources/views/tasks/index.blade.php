<x-layouts.app :title="__('Tasks')">
    <x-page-header :title="__('Tasks')" :subtitle="__('Assignments, deadlines, progress and reviews.')">
        <x-slot:actions>
            <a href="{{ route('tasks.index', ['mine' => 1, 'status' => 'open']) }}" class="btn">{{ __('My open tasks') }}</a>
            @can('create', \App\Models\Task::class)
                <a href="{{ route('tasks.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('New task') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>
    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Title or description')" />
            <x-filter-select name="status" :label="__('Status')" :options="$statuses" />
            <x-filter-select name="priority" :label="__('Priority')" :options="\App\Support\Status::options('priority')" />
            <x-filter-select name="assignee" :label="__('Assigned to')" :options="$users" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="project" :label="__('Project')" :options="$projects" />
            <x-filter-select name="overdue" :label="__('Deadline')" :options="['1' => __('Overdue only')]" />
            <x-filter-select name="mine" :label="__('Show')" :options="['1' => __('Assigned to me')]" />
        </x-filters>
        @if ($tasks->isEmpty())
            <x-empty icon="check" :message="__('No tasks found.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="title" :label="__('Task')" /></th>
                    <th>{{ __('Assigned to') }}</th>
                    <th>{{ __('Priority') }}</th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                    <th style="min-width:120px"><x-sort-link column="progress" :label="__('Progress')" /></th>
                    <th><x-sort-link column="due_date" :label="__('Due date')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($tasks as $task)
                    <tr>
                        <td class="title-cell"><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                            @if ($task->project)<span class="sub">{{ $task->project->code }} — {{ $task->project->title }}</span>@endif</td>
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
</x-layouts.app>
