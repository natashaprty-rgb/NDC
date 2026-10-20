<div class="stack">
    <div class="card">
        <div class="card__head"><h2>{{ __('Contractors, consultants, stakeholders and contacts') }}</h2></div>
        @if ($stakeholders->isEmpty())
            <x-empty icon="users" :message="__('No stakeholders have been recorded.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Type') }}</th><th>{{ __('Name') }}</th><th>{{ __('Role in the project') }}</th><th>{{ __('Contact') }}</th>
                    <th>{{ __('Notes') }}</th>@if ($canUpdate)<th></th>@endif
                </tr></thead>
                <tbody>
                @foreach ($stakeholders as $stakeholder)
                    <tr>
                        <td><x-status domain="stakeholder_type" :value="$stakeholder->type" /></td>
                        <td class="title-cell">{{ $stakeholder->name }}@if ($stakeholder->organization)<span class="sub">{{ $stakeholder->organization }}</span>@endif</td>
                        <td>{{ $stakeholder->role ?: '—' }}</td>
                        <td class="small">
                            @if ($stakeholder->email)<a href="mailto:{{ $stakeholder->email }}">{{ $stakeholder->email }}</a><br>@endif
                            {{ $stakeholder->phone }}
                            @if (! $stakeholder->email && ! $stakeholder->phone)—@endif
                        </td>
                        <td class="small prose">{{ $stakeholder->notes ?: '—' }}</td>
                        @if ($canUpdate)
                            <td class="actions">
                                <a href="{{ route('projects.stakeholders.edit', [$project, $stakeholder]) }}" class="btn btn-sm">{{ __('Edit') }}</a>
                                <x-delete-button :action="route('projects.stakeholders.destroy', [$project, $stakeholder])" :label="__('Remove')" />
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    @if ($canUpdate)
        <form method="POST" action="{{ route('projects.stakeholders.store', $project) }}" class="card form">
            @csrf
            <div class="card__head"><h2>{{ __('Add stakeholder') }}</h2></div>
            <div class="card__body">
                @include('projects.stakeholders._fields', ['stakeholder' => new \App\Models\ProjectStakeholder(['type' => 'stakeholder'])])
            </div>
            <div class="card__foot form-actions"><button class="btn btn-primary"><x-icon name="plus" /> {{ __('Add stakeholder') }}</button></div>
        </form>
    @endif
</div>
