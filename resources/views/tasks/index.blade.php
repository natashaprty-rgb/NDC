<x-layout :title="__('tasks.title')" :breadcrumbs="[__('tasks.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Task::class)
            <a href="{{ route('tasks.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('tasks.new') }}</a>
        @endcan
    </x-slot:actions>

    <nav class="tabs">
        @foreach($scopes as $s)
            <a href="{{ route('tasks.index', ['scope' => $s]) }}" class="{{ $scope === $s ? 'is-active' : '' }}" @if($scope === $s) aria-current="page" @endif>{{ __('tasks.scope_'.$s) }}</a>
        @endforeach
    </nav>

    <section class="card">
        <x-filter-bar :action="route('tasks.index')">
            <input type="hidden" name="scope" value="{{ $scope }}">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('tasks.search_hint')" />
            <x-field.select name="status" :label="__('common.status')" :options="['open' => __('tasks.status_open')] + enum_options(\App\Models\Task::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="priority" :label="__('tasks.priority')" :options="enum_options(\App\Models\Task::PRIORITIES)" :value="request('priority')" :placeholder="__('common.all')" />
            <x-field.select name="project" :label="__('common.project')" :options="$projects" :value="request('project')" :placeholder="__('common.all')" />
            <div class="field"><span class="label">&nbsp;</span>
                <label class="checkbox"><input type="checkbox" name="overdue" value="1" @checked(request()->boolean('overdue'))> {{ __('tasks.overdue_only') }}</label>
            </div>
        </x-filter-bar>

        @if($tasks->isEmpty())
            <x-empty :message="__('tasks.empty')" icon="list" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('common.reference') }}</th><th>{{ __('common.title') }}</th><th>{{ __('common.assigned_to') }}</th>
                        <th>{{ __('tasks.priority') }}</th><th>{{ __('tasks.due_date') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.progress') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($tasks as $task)
                        <tr class="{{ $task->isOverdue() ? 'row-overdue' : '' }}">
                            <td class="nowrap">{{ $task->reference }}</td>
                            <td><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>
                                @if($task->project)<div class="small muted">{{ $task->project->name }}</div>@endif</td>
                            <td>{{ $task->assignee?->name ?? __('common.unassigned') }}</td>
                            <td><x-badge :value="$task->priority" /></td>
                            <td class="nowrap">{{ fmt_date($task->due_date) }} @if($task->isOverdue())<x-badge value="overdue" />@endif</td>
                            <td><x-badge :value="$task->status" /></td>
                            <td style="min-width:120px"><x-progress :value="$task->progress" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $tasks->links() }}
        @endif
    </section>
</x-layout>
