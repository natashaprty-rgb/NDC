<div class="card" id="assignments">
    <div class="card__head"><h2>{{ __('Assignment history') }}</h2>
        <span class="muted small">{{ trans_choice(':count record|:count records', $asset->assignments->count()) }}</span></div>
    @if ($asset->assignments->isEmpty())
        <x-empty icon="users" :message="__('This asset has never been assigned.')" />
    @else
        <div class="table-wrap"><table class="table">
            <thead><tr>
                <th>{{ __('Assigned to') }}</th>
                <th>{{ __('Assigned') }}</th>
                <th>{{ __('Returned') }}</th>
                <th>{{ __('Condition on return') }}</th>
            </tr></thead>
            <tbody>
            @foreach ($asset->assignments as $assignment)
                <tr>
                    <td>{{ $assignment->user?->name ?? __('Department pool') }}
                        <span class="sub">{{ collect([$assignment->department?->displayName(), $assignment->location])->filter()->join(' · ') }}</span>
                        @if ($assignment->notes)<span class="sub">{{ $assignment->notes }}</span>@endif</td>
                    <td class="nowrap">{{ fmt_date($assignment->assigned_on) }}
                        <span class="sub">{{ $assignment->assigner?->name }}</span></td>
                    <td class="nowrap">@if ($assignment->returned_on){{ fmt_date($assignment->returned_on) }}@else<span class="badge badge--info">{{ __('Current') }}</span>@endif</td>
                    <td>@if ($assignment->condition_on_return)<x-status domain="asset_condition" :value="$assignment->condition_on_return" />@else — @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
