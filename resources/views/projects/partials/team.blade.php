<div class="stack">
    <div class="card">
        <div class="card__head"><h2>{{ __('Project team') }}</h2>
            <span class="muted small">{{ __('Project manager') }}: {{ $project->manager?->name ?? '—' }}</span></div>
        @if ($members->isEmpty())
            <x-empty icon="users" :message="__('No team members have been added.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Department') }}</th><th>{{ __('Role in the project') }}</th><th>{{ __('Added') }}</th>@if ($canUpdate)<th></th>@endif</tr></thead>
                <tbody>
                @foreach ($members as $member)
                    <tr>
                        <td class="title-cell">{{ $member->name }}@if ($member->job_title)<span class="sub">{{ $member->job_title }}</span>@endif</td>
                        <td>{{ $member->department?->displayName() ?? '—' }}</td>
                        <td>{{ $member->pivot->role ?: '—' }}</td>
                        <td class="nowrap">{{ fmt_date($member->pivot->created_at) }}</td>
                        @if ($canUpdate)
                            <td class="actions">
                                <x-delete-button :action="route('projects.members.destroy', [$project, $member])" :label="__('Remove')"
                                    :confirm="__('Remove :name from the project team?', ['name' => $member->name])" />
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    @if ($canUpdate)
        <form method="POST" action="{{ route('projects.members.store', $project) }}" class="card form">
            @csrf
            <div class="card__head"><h2>{{ __('Add team member') }}</h2></div>
            <div class="card__body form-grid">
                <x-form.select name="user_id" :label="__('Staff member')" :options="$users" required />
                <x-form.input name="role" :label="__('Role in the project')" maxlength="100" :help="__('For example: Technical lead, Data analyst.')" />
            </div>
            <div class="card__foot form-actions"><button class="btn btn-primary"><x-icon name="plus" /> {{ __('Add to team') }}</button></div>
        </form>
    @endif
</div>
