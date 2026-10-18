@php $canManage = auth()->user()->can('manageDetails', $project); @endphp
<x-layout :title="$project->name" :subtitle="$project->reference"
          :breadcrumbs="[__('projects.title') => route('projects.index'), $project->name]">
    <x-slot:actions>
        <a href="{{ route('projects.report', $project) }}" class="btn"><x-icon name="printer" width="16" height="16" /> {{ __('projects.report') }}</a>
        @can('update', $project)
            <a href="{{ route('projects.edit', $project) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('archive', $project)
            <x-delete-button :action="route('projects.archive', $project)" method="POST" :label="__('common.archive')" :message="__('common.confirm_archive')" class="btn btn--danger" />
        @endcan
        @can('restore', $project)
            <x-delete-button :action="route('projects.restore', $project)" method="POST" :label="__('projects.restore')" :message="__('projects.confirm_restore')" class="btn" />
        @endcan
    </x-slot:actions>

    @if($project->isArchived())
        <div class="alert alert--warning">{{ __('projects.archived_notice') }}</div>
    @endif

    <nav class="tabs" aria-label="{{ __('projects.overview') }}">
        <a href="#overview" class="is-active">{{ __('projects.overview') }}</a>
        <a href="#milestones">{{ __('projects.milestones') }}</a>
        <a href="#tasks">{{ __('projects.tasks') }}</a>
        <a href="#risks">{{ __('projects.risks') }}</a>
        <a href="#stakeholders">{{ __('projects.stakeholders') }}</a>
    </nav>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('projects.overview')" id="overview">
                <div class="mb-1"><x-progress :value="$progress" /></div>
                <p class="small muted">{{ __('projects.progress_note') }}</p>
                @if($project->description)<h3>{{ __('common.description') }}</h3><p class="prose">{{ $project->description }}</p>@endif
                @if($project->objectives)<h3>{{ __('projects.objectives') }}</h3><p class="prose mb-0">{{ $project->objectives }}</p>@endif
            </x-card>

            {{-- Milestones --}}
            <x-card :title="__('projects.milestones')" :flush="true" id="milestones">
                @if($project->milestones->isEmpty())
                    <x-empty :message="__('projects.no_milestones')" icon="clipboard" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.title') }}</th><th>{{ __('projects.due_date') }}</th><th>{{ __('common.status') }}</th>@if($canManage)<th></th>@endif</tr></thead>
                        <tbody>
                        @foreach($project->milestones as $milestone)
                            <tr>
                                <td>
                                    <strong>{{ $milestone->title }}</strong>
                                    @if($milestone->deliverables)<div class="small muted prose">{{ $milestone->deliverables }}</div>@endif
                                    @if($milestone->completed_at)<div class="small muted">{{ __('projects.completed_on', ['date' => fmt_date($milestone->completed_at)]) }}</div>@endif
                                </td>
                                <td class="nowrap">{{ fmt_date($milestone->due_date) }}</td>
                                <td><x-badge :value="$milestone->status" /></td>
                                @if($canManage)
                                <td class="text-right nowrap">
                                    <details><summary class="btn btn--sm">{{ __('projects.edit_item') }}</summary>
                                        <form method="POST" action="{{ route('projects.milestones.update', [$project, $milestone]) }}" class="stack mt-1" style="min-width:260px;text-align:left">
                                            @csrf @method('PUT')
                                            <x-field.input name="title" :label="__('common.title')" :value="$milestone->title" :required="true" />
                                            <x-field.input name="due_date" type="date" :label="__('projects.due_date')" :value="$milestone->due_date" />
                                            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\ProjectMilestone::STATUSES)" :value="$milestone->status" :placeholder="false" />
                                            <x-field.textarea name="deliverables" :label="__('projects.deliverables')" :value="$milestone->deliverables" rows="2" />
                                            <button class="btn btn--primary btn--sm" type="submit">{{ __('common.save') }}</button>
                                        </form>
                                    </details>
                                    <x-delete-button :action="route('projects.milestones.destroy', [$project, $milestone])" :label="__('common.remove')" :message="__('projects.confirm_remove_item')" />
                                </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                @if($canManage)
                    <x-slot:footer>
                        <details><summary class="btn btn--sm"><x-icon name="plus" width="14" height="14" /> {{ __('projects.add_milestone') }}</summary>
                            <form method="POST" action="{{ route('projects.milestones.store', $project) }}" class="form-grid mt-1">
                                @csrf
                                <x-field.input name="title" :label="__('common.title')" :required="true" maxlength="200" />
                                <x-field.input name="due_date" type="date" :label="__('projects.due_date')" />
                                <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\ProjectMilestone::STATUSES)" value="pending" :placeholder="false" />
                                <x-field.input name="sort_order" type="number" :label="__('projects.sort_order')" value="0" min="0" />
                                <x-field.textarea name="deliverables" :label="__('projects.deliverables')" rows="2" class="span-2" />
                                <div class="span-2 text-right"><button class="btn btn--primary btn--sm" type="submit">{{ __('common.add') }}</button></div>
                            </form>
                        </details>
                    </x-slot:footer>
                @endif
            </x-card>

            {{-- Tasks --}}
            <x-card :title="__('projects.tasks')" :flush="true" id="tasks">
                <x-slot:headerActions>
                    @if(! $project->isArchived())
                        @can('create', \App\Models\Task::class)
                            <a href="{{ route('tasks.create', ['project_id' => $project->id]) }}" class="btn btn--sm btn--primary">{{ __('projects.add_task') }}</a>
                        @endcan
                    @endif
                    <a href="{{ route('tasks.index', ['scope' => auth()->user()->can('tasks.view_all') ? 'all' : 'mine', 'project' => $project->id]) }}" class="btn btn--sm">{{ __('common.view_all') }}</a>
                </x-slot:headerActions>
                @if($tasks->isEmpty())
                    <x-empty :message="__('projects.no_tasks')" icon="list" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('common.title') }}</th><th>{{ __('common.assigned_to') }}</th><th>{{ __('projects.due_date') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                        <tbody>@foreach($tasks as $task)
                            <tr class="{{ $task->isOverdue() ? 'row-overdue' : '' }}">
                                <td class="nowrap">{{ $task->reference }}</td>
                                <td><a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a></td>
                                <td>{{ $task->assignee?->name ?? __('common.unassigned') }}</td>
                                <td class="nowrap">{{ fmt_date($task->due_date) }}</td>
                                <td><x-badge :value="$task->status" /></td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </x-card>

            {{-- Risks & issues --}}
            <x-card :title="__('projects.risks')" :flush="true" id="risks">
                @if($project->risks->isEmpty())
                    <x-empty :message="__('projects.no_risks')" icon="alert" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.title') }}</th><th>{{ __('common.type') }}</th><th>{{ __('projects.severity') }}</th><th>{{ __('projects.risk_owner') }}</th><th>{{ __('common.status') }}</th>@if($canManage)<th></th>@endif</tr></thead>
                        <tbody>
                        @foreach($project->risks as $risk)
                            <tr>
                                <td>
                                    <strong>{{ $risk->title }}</strong>
                                    @if($risk->description)<div class="small prose">{{ $risk->description }}</div>@endif
                                    @if($risk->corrective_action)<div class="small muted"><strong>{{ __('projects.corrective_action') }}:</strong> {{ $risk->corrective_action }}</div>@endif
                                    @if($risk->due_date)<div class="small muted">{{ __('projects.due_date') }}: {{ fmt_date($risk->due_date) }}</div>@endif
                                    @if($risk->closed_at)<div class="small muted">{{ __('projects.closed_on', ['date' => fmt_date($risk->closed_at)]) }}</div>@endif
                                </td>
                                <td>{{ status_label($risk->type) }}</td>
                                <td class="nowrap">
                                    <x-badge :value="$risk->severity() >= 6 ? 'high' : ($risk->severity() >= 3 ? 'medium' : 'low')" :label="$risk->severity().' / 9'" />
                                    <div class="small muted">{{ __('projects.likelihood') }}: {{ status_label($risk->likelihood) }}<br>{{ __('projects.impact') }}: {{ status_label($risk->impact) }}</div>
                                </td>
                                <td>{{ $risk->owner?->name ?? '—' }}</td>
                                <td><x-badge :value="$risk->status" /></td>
                                @if($canManage)
                                <td class="text-right nowrap">
                                    <details><summary class="btn btn--sm">{{ __('projects.edit_item') }}</summary>
                                        <form method="POST" action="{{ route('projects.risks.update', [$project, $risk]) }}" class="stack mt-1" style="min-width:280px;text-align:left">
                                            @csrf @method('PUT')
                                            @include('projects.partials.risk-fields', ['risk' => $risk])
                                            <button class="btn btn--primary btn--sm" type="submit">{{ __('common.save') }}</button>
                                        </form>
                                    </details>
                                    <x-delete-button :action="route('projects.risks.destroy', [$project, $risk])" :label="__('common.remove')" :message="__('projects.confirm_remove_item')" />
                                </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                @if($canManage)
                    <x-slot:footer>
                        <details><summary class="btn btn--sm"><x-icon name="plus" width="14" height="14" /> {{ __('projects.add_risk') }}</summary>
                            <form method="POST" action="{{ route('projects.risks.store', $project) }}" class="stack mt-1">
                                @csrf
                                @include('projects.partials.risk-fields', ['risk' => new \App\Models\ProjectRisk(['type' => 'risk', 'likelihood' => 'medium', 'impact' => 'medium', 'status' => 'open'])])
                                <div class="text-right"><button class="btn btn--primary btn--sm" type="submit">{{ __('common.add') }}</button></div>
                            </form>
                        </details>
                    </x-slot:footer>
                @endif
            </x-card>

            {{-- Stakeholders --}}
            <x-card :title="__('projects.stakeholders')" :flush="true" id="stakeholders">
                @if($project->stakeholders->isEmpty())
                    <x-empty :message="__('projects.no_stakeholders')" icon="users" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.name') }}</th><th>{{ __('common.type') }}</th><th>{{ __('projects.role_description') }}</th><th>{{ __('projects.contact') }}</th>@if($canManage)<th></th>@endif</tr></thead>
                        <tbody>
                        @foreach($project->stakeholders as $stakeholder)
                            <tr>
                                <td><strong>{{ $stakeholder->name }}</strong>
                                    @if($stakeholder->organization)<div class="small muted">{{ $stakeholder->organization }}</div>@endif
                                    @if($stakeholder->supplier)<div class="small">{{ __('projects.supplier') }}: @if(route_if('suppliers.show', $stakeholder->supplier))<a href="{{ route('suppliers.show', $stakeholder->supplier) }}">{{ $stakeholder->supplier->name }}</a>@else{{ $stakeholder->supplier->name }}@endif</div>@endif
                                </td>
                                <td>{{ status_label($stakeholder->type) }}</td>
                                <td>{{ $stakeholder->role_description ?? '—' }}</td>
                                <td class="small">{{ $stakeholder->email }}<br>{{ $stakeholder->phone }}</td>
                                @if($canManage)
                                <td class="text-right nowrap">
                                    <details><summary class="btn btn--sm">{{ __('projects.edit_item') }}</summary>
                                        <form method="POST" action="{{ route('projects.stakeholders.update', [$project, $stakeholder]) }}" class="stack mt-1" style="min-width:260px;text-align:left">
                                            @csrf @method('PUT')
                                            @include('projects.partials.stakeholder-fields', ['stakeholder' => $stakeholder])
                                            <button class="btn btn--primary btn--sm" type="submit">{{ __('common.save') }}</button>
                                        </form>
                                    </details>
                                    <x-delete-button :action="route('projects.stakeholders.destroy', [$project, $stakeholder])" :label="__('common.remove')" :message="__('projects.confirm_remove_item')" />
                                </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                @if($canManage)
                    <x-slot:footer>
                        <details><summary class="btn btn--sm"><x-icon name="plus" width="14" height="14" /> {{ __('projects.add_stakeholder') }}</summary>
                            <form method="POST" action="{{ route('projects.stakeholders.store', $project) }}" class="stack mt-1">
                                @csrf
                                @include('projects.partials.stakeholder-fields', ['stakeholder' => new \App\Models\ProjectStakeholder(['type' => 'stakeholder'])])
                                <div class="text-right"><button class="btn btn--primary btn--sm" type="submit">{{ __('common.add') }}</button></div>
                            </form>
                        </details>
                    </x-slot:footer>
                @endif
            </x-card>

            <x-attachments :model="$project" />
        </div>

        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$project->status" /> @if($project->isOverdue())<x-badge value="overdue" />@endif</dd>
                    <dt>{{ __('projects.priority') }}</dt><dd><x-badge :value="$project->priority" /></dd>
                    <dt>{{ __('projects.manager') }}</dt><dd>{{ $project->manager?->name ?? '—' }}</dd>
                    <dt>{{ __('common.department') }}</dt><dd>{{ $project->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('projects.start_date') }}</dt><dd>{{ fmt_date($project->start_date) }}</dd>
                    <dt>{{ __('projects.expected_end_date') }}</dt><dd>{{ fmt_date($project->expected_end_date) }}</dd>
                    <dt>{{ __('projects.actual_end_date') }}</dt><dd>{{ fmt_date($project->actual_end_date) }}</dd>
                </dl>
            </x-card>

            <x-card :title="__('projects.task_summary')">
                @if($taskStats->sum() === 0)
                    <p class="muted mb-0">{{ __('projects.no_tasks') }}</p>
                @else
                    <dl class="dl">
                        @foreach(\App\Models\Task::STATUSES as $status)
                            <dt>{{ status_label($status) }}</dt><dd>{{ $taskStats->get($status, 0) }}</dd>
                        @endforeach
                    </dl>
                @endif
            </x-card>

            @can('budgets.view')
                <x-card :title="__('projects.budget')">
                    @if($budgets->isEmpty())
                        <p class="muted mb-0">{{ __('projects.no_budgets') }}</p>
                    @else
                        @foreach($budgets as $row)
                            <div class="mb-1">
                                <strong>@if(route_if('budgets.show', $row['budget']))<a href="{{ route('budgets.show', $row['budget']) }}">{{ $row['budget']->title }}</a>@else{{ $row['budget']->title }}@endif</strong>
                                <span class="small muted">{{ __('projects.fiscal_year') }} {{ $row['budget']->fiscal_year }} · <x-badge :value="$row['budget']->status" /></span>
                                <dl class="dl small mt-1">
                                    <dt>{{ __('projects.allocated') }}</dt><dd class="nowrap">{{ money($row['summary']['allocated'], $row['budget']->currency) }}</dd>
                                    <dt>{{ __('projects.recorded') }}</dt><dd class="nowrap">{{ money($row['summary']['recorded'], $row['budget']->currency) }}</dd>
                                    <dt>{{ __('projects.approved') }}</dt><dd class="nowrap">{{ money($row['summary']['approved'], $row['budget']->currency) }}</dd>
                                    <dt>{{ __('projects.remaining') }}</dt><dd class="nowrap">{{ money($row['summary']['remaining'], $row['budget']->currency) }}</dd>
                                </dl>
                            </div>
                        @endforeach
                    @endif
                </x-card>
            @endcan

            @if($documentCount !== null)
                <x-card :title="__('projects.documents')">
                    <p class="mb-1">{{ __('projects.documents_count', ['count' => $documentCount]) }}</p>
                    @if(route_if('documents.index'))
                        <a href="{{ route('documents.index', ['project' => $project->id]) }}" class="btn btn--sm">{{ __('projects.view_documents') }}</a>
                    @endif
                </x-card>
            @endif

            <x-history :model="$project" />
        </div>
    </div>
</x-layout>
