<x-layouts.app :title="__('Allocation report')">
    <x-page-header :title="__('Allocation report')" :subtitle="__('Assets currently issued, grouped by department and holder, as recorded on :date.', ['date' => fmt_date(now(), true)])" :breadcrumbs="[__('Assets') => route('assets.index')]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <x-filters>
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="category" :label="__('Category')" :options="$categories" />
        </x-filters>
        <div class="card__body">
            <dl class="dl">
                <dt>{{ __('Assets issued') }}</dt><dd>{{ $assignedCount }}</dd>
                <dt>{{ __('Assets in store (not issued)') }}</dt><dd>{{ $unassignedCount }}</dd>
            </dl>
        </div>
    </div>

    @forelse ($byDepartment as $department => $holders)
        <div class="card">
            <div class="card__head"><h2>{{ $department }}</h2><span class="muted small">{{ trans_choice(':count asset|:count assets', $holders->sum(fn ($c) => $c->count())) }}</span></div>
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Holder') }}</th><th>{{ __('Asset tag') }}</th><th>{{ __('Asset') }}</th><th>{{ __('Category') }}</th>
                    <th>{{ __('Location') }}</th><th>{{ __('Since') }}</th><th>{{ __('Condition') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($holders as $holder => $assets)
                    @foreach ($assets as $asset)
                        <tr>
                            <td>@if ($loop->first)<strong>{{ $holder }}</strong>@endif</td>
                            <td class="nowrap"><a href="{{ route('assets.show', $asset) }}">{{ $asset->asset_tag }}</a></td>
                            <td>{{ $asset->name }}<span class="sub mono">{{ $asset->serial_number }}</span></td>
                            <td>{{ $asset->category?->displayName() ?? '—' }}</td>
                            <td>{{ $asset->openAssignment?->location ?? $asset->location ?? '—' }}</td>
                            <td class="nowrap">{{ fmt_date($asset->openAssignment?->assigned_on) }}</td>
                            <td>{{ \App\Support\Status::label('asset_condition', $asset->condition) }}</td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table></div>
        </div>
    @empty
        <div class="card"><x-empty icon="users" :message="__('No assets are currently issued.')" /></div>
    @endforelse
</x-layouts.app>
