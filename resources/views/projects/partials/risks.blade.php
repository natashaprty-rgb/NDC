<div class="stack">
    <div class="card">
        <div class="card__head"><h2>{{ __('Risks, challenges and delays') }}</h2>
            <span class="muted small">{{ __(':count open', ['count' => $project->open_risks_count]) }}</span></div>
        @if ($risks->isEmpty())
            <x-empty icon="alert" :message="__('No risks, challenges or delays have been recorded.')" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Type') }}</th><th>{{ __('Title') }}</th><th>{{ __('Likelihood') }}</th><th>{{ __('Impact') }}</th>
                    <th>{{ __('Corrective action') }}</th><th>{{ __('Responsible person') }}</th><th>{{ __('Status') }}</th>@if ($canUpdate)<th></th>@endif
                </tr></thead>
                <tbody>
                @foreach ($risks as $risk)
                    <tr>
                        <td><x-status domain="risk_type" :value="$risk->type" /></td>
                        <td class="title-cell">{{ $risk->title }}
                            @if ($risk->description)<span class="sub prose">{{ $risk->description }}</span>@endif
                            <span class="sub">{{ __('Identified :date', ['date' => fmt_date($risk->identified_on ?? $risk->created_at)]) }}@if ($risk->resolved_on) · {{ __('Resolved :date', ['date' => fmt_date($risk->resolved_on)]) }}@endif</span></td>
                        <td><x-status domain="level" :value="$risk->likelihood" /></td>
                        <td><x-status domain="level" :value="$risk->impact" /></td>
                        <td class="small prose">{{ $risk->corrective_action ?: '—' }}</td>
                        <td>{{ $risk->owner?->name ?? '—' }}</td>
                        <td><x-status domain="risk" :value="$risk->status" /></td>
                        @if ($canUpdate)
                            <td class="actions">
                                <a href="{{ route('projects.risks.edit', [$project, $risk]) }}" class="btn btn-sm">{{ __('Edit') }}</a>
                                <x-delete-button :action="route('projects.risks.destroy', [$project, $risk])" />
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
    @if ($canUpdate)
        <form method="POST" action="{{ route('projects.risks.store', $project) }}" class="card form">
            @csrf
            <div class="card__head"><h2>{{ __('Record a risk, challenge or delay') }}</h2></div>
            <div class="card__body">
                @include('projects.risks._fields', ['risk' => new \App\Models\ProjectRisk(['identified_on' => today()])])
            </div>
            <div class="card__foot form-actions"><button class="btn btn-primary"><x-icon name="plus" /> {{ __('Add to register') }}</button></div>
        </form>
    @endif
</div>
