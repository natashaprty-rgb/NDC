<x-layouts.app :title="$staff->full_name">
    <x-page-header :title="$staff->full_name" :subtitle="collect([$staff->job_title, $staff->department?->displayName()])->filter()->join(' · ') ?: null"
        :breadcrumbs="[__('Staff') => route('staff.index')]">
        <x-slot:meta>
            <p><span class="mono">{{ $staff->staff_number }}</span> · <x-status domain="employment" :value="$staff->employment_status" /></p>
        </x-slot:meta>
        <x-slot:actions>
            @can('update', $staff)
                <a href="{{ route('staff.edit', $staff) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endcan
            @can('delete', $staff)
                <x-delete-button :action="route('staff.destroy', $staff)" :label="__('Delete')" class="btn btn-danger"
                    :confirm="__('Delete this staff record? It will be removed from the directory.')" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Profile') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Staff number') }}</dt><dd class="mono">{{ $staff->staff_number }}</dd>
                        <dt>{{ __('Job title') }}</dt><dd>{{ $staff->job_title ?? '—' }}</dd>
                        <dt>{{ __('Department') }}</dt><dd>
                            @if ($staff->department)
                                <a href="{{ route('staff.index', ['department' => $staff->department_id]) }}">{{ $staff->department->displayName() }}</a>
                                @if ($staff->department->head)<span class="muted small">· {{ __('Head') }}: {{ $staff->department->head->name }}</span>@endif
                            @else — @endif
                        </dd>
                        <dt>{{ __('Supervisor') }}</dt><dd>@if ($staff->supervisor)<a href="{{ route('staff.show', $staff->supervisor) }}">{{ $staff->supervisor->full_name }}</a>@else — @endif</dd>
                        <dt>{{ __('Employment status') }}</dt><dd><x-status domain="employment" :value="$staff->employment_status" /></dd>
                        <dt>{{ __('Employment type') }}</dt><dd>{{ \App\Support\Status::label('employment_type', $staff->employment_type) }}</dd>
                        <dt>{{ __('Hire date') }}</dt><dd>{{ fmt_date($staff->hire_date) }}</dd>
                        <dt>{{ __('End date') }}</dt><dd>{{ fmt_date($staff->end_date) }}</dd>
                        <dt>{{ __('Official e-mail') }}</dt><dd>@if ($staff->email)<a href="mailto:{{ $staff->email }}">{{ $staff->email }}</a>@else — @endif</dd>
                        <dt>{{ __('Official phone') }}</dt><dd>{{ $staff->phone ?? '—' }}</dd>
                        <dt>{{ __('Gender') }}</dt><dd>{{ $staff->gender ? __(\App\Models\Staff::GENDERS[$staff->gender] ?? $staff->gender) : '—' }}</dd>
                        <dt>{{ __('System user account') }}</dt><dd>
                            @if ($staff->user)
                                @can('users.manage')<a href="{{ route('admin.users.show', $staff->user) }}">{{ $staff->user->name }}</a>@else{{ $staff->user->name }}@endcan
                                <span class="muted small">{{ $staff->user->email }}</span>
                            @else — @endif
                        </dd>
                        @if ($staff->notes)
                            <dt>{{ __('Notes') }}</dt><dd class="prose">{{ $staff->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card" id="trainings">
                <div class="card__head">
                    <h2>{{ __('Training and professional development') }}</h2>
                    <span class="muted small">{{ trans_choice(':count record|:count records', $staff->trainings->count()) }}</span>
                </div>
                @if ($staff->trainings->isEmpty())
                    <x-empty icon="file" :message="__('No training recorded yet.')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr>
                            <th>{{ __('Training or programme') }}</th><th>{{ __('Type') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Certificate') }}</th>
                            @can('manageTrainings', $staff)<th></th>@endcan
                        </tr></thead>
                        <tbody>
                        @foreach ($staff->trainings as $training)
                            <tr>
                                <td class="title-cell">{{ $training->title }}<span class="sub">{{ $training->provider }}</span>
                                    @if ($training->notes)<span class="sub prose">{{ $training->notes }}</span>@endif</td>
                                <td>{{ $training->typeLabel() }}</td>
                                <td class="nowrap">{{ fmt_date($training->start_date) }}@if ($training->end_date && ! $training->end_date->equalTo($training->start_date)) – {{ fmt_date($training->end_date) }}@endif</td>
                                <td class="mono">{{ $training->certificate_number ?? '—' }}</td>
                                @can('manageTrainings', $staff)
                                    <td class="actions">
                                        <a href="{{ route('staff.trainings.edit', [$staff, $training]) }}" class="btn btn-sm">{{ __('Edit') }}</a>
                                        <x-delete-button :action="route('staff.trainings.destroy', [$staff, $training])" :label="__('Remove')" />
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                @can('manageTrainings', $staff)
                    <div class="card__foot">
                        <form method="POST" action="{{ route('staff.trainings.store', $staff) }}">
                            @csrf
                            <h3 class="small">{{ __('Record training') }}</h3>
                            @include('staff.trainings._fields', ['training' => new \App\Models\StaffTraining()])
                            <div class="form-actions"><button class="btn btn-primary"><x-icon name="plus" /> {{ __('Add training record') }}</button></div>
                        </form>
                    </div>
                @endcan
            </div>

            @if ($staff->user_id && auth()->user()->can('tasks.view'))
                <div class="card" id="tasks">
                    <div class="card__head"><h2>{{ __('Assigned tasks') }}</h2></div>
                    @if ($tasks->isEmpty())
                        <x-empty icon="check" :message="__('No tasks assigned that you can view.')" />
                    @else
                        <div class="table-wrap"><table class="table">
                            <thead><tr><th>{{ __('Task') }}</th><th>{{ __('Project') }}</th><th>{{ __('Due date') }}</th><th>{{ __('Status') }}</th></tr></thead>
                            <tbody>
                            @foreach ($tasks as $task)
                                <tr>
                                    <td class="title-cell">@if (Route::has('tasks.show'))<a href="{{ route('tasks.show', $task) }}">{{ $task->title }}</a>@else{{ $task->title }}@endif</td>
                                    <td>{{ $task->project?->title ?? '—' }}</td>
                                    <td class="nowrap">{{ fmt_date($task->due_date) }} @if ($task->isOverdue())<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</td>
                                    <td><x-status domain="task" :value="$task->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                    @endif
                </div>
            @endif

            @if ($canSeeFiles)
                <x-attachments :model="$staff" :can-upload="auth()->user()->can('update', $staff)" :title="__('CVs, certificates and files')" />
            @endif

            @if (auth()->user()->can('update', $staff) || auth()->user()->can('audit.view'))
                <x-audit-trail :model="$staff" />
            @endif
        </div>

        <div class="stack">
            @if ($canSeeSensitive)
                <div class="card">
                    <div class="card__head"><h2><x-icon name="lock" /> {{ __('Sensitive personal information') }}</h2></div>
                    <div class="card__body">
                        <dl class="dl">
                            <dt>{{ __('Date of birth') }}</dt><dd>{{ fmt_date($staff->date_of_birth) }}</dd>
                            <dt>{{ __('National ID number') }}</dt><dd>{{ $staff->national_id ?? '—' }}</dd>
                            <dt>{{ __('Personal phone') }}</dt><dd>{{ $staff->personal_phone ?? '—' }}</dd>
                            <dt>{{ __('Home address') }}</dt><dd class="prose">{{ $staff->address ?? '—' }}</dd>
                            <dt>{{ __('Emergency contact') }}</dt><dd>{{ $staff->emergency_contact ?? '—' }}</dd>
                        </dl>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card__head"><h2>{{ __('Direct reports') }}</h2></div>
                @if ($directReports->isEmpty())
                    <x-empty icon="users" :message="__('No staff report to this person.')" />
                @else
                    <ul class="list">
                        @foreach ($directReports as $report)
                            <li><a href="{{ route('staff.show', $report) }}">{{ $report->full_name }}</a>
                                <div class="list__meta">{{ $report->job_title ?? '—' }} · <span class="mono">{{ $report->staff_number }}</span></div></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
