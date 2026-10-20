<x-layouts.app :title="__('Inventory report')">
    <x-page-header :title="__('Inventory report')" :subtitle="__('Assets grouped by category, as recorded on :date.', ['date' => fmt_date(now(), true)])" :breadcrumbs="[__('Assets') => route('assets.index')]">
        <x-slot:actions>
            <button type="button" class="btn btn-primary" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <x-filters>
            <x-filter-select name="category" :label="__('Category')" :options="$categories" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('asset')" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="condition" :label="__('Condition')" :options="\App\Support\Status::options('asset_condition')" />
            <x-filter-select name="warranty" :label="__('Warranty')" :options="$warrantyOptions" />
        </x-filters>
        <div class="card__body">
            <dl class="dl">
                <dt>{{ __('Total assets') }}</dt><dd>{{ $total }}</dd>
                <dt>{{ __('Recorded purchase value') }}</dt>
                <dd>{{ $valueByCurrency->isEmpty() ? '—' : $valueByCurrency->map(fn ($sum, $cur) => money($sum, $cur))->join(' · ') }}</dd>
            </dl>
            @unless (request('status'))
                <p class="small muted mb-0">{{ __('Disposed assets are excluded unless selected in the status filter.') }}</p>
            @endunless
        </div>
    </div>

    @forelse ($groups as $category => $items)
        <div class="card">
            <div class="card__head"><h2>{{ $category }}</h2><span class="muted small">{{ trans_choice(':count asset|:count assets', $items->count()) }}</span></div>
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th>{{ __('Asset tag') }}</th><th>{{ __('Asset') }}</th><th>{{ __('Serial number') }}</th>
                    <th>{{ __('Department') }}</th><th>{{ __('Location') }}</th><th>{{ __('Assigned to') }}</th>
                    <th>{{ __('Condition') }}</th><th>{{ __('Status') }}</th><th>{{ __('Warranty expiry') }}</th><th class="text-right">{{ __('Purchase cost') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($items as $asset)
                    <tr>
                        <td class="nowrap"><a href="{{ route('assets.show', $asset) }}">{{ $asset->asset_tag }}</a></td>
                        <td>{{ $asset->name }}<span class="sub">{{ collect([$asset->manufacturer, $asset->model])->filter()->join(' ') }}</span></td>
                        <td class="mono">{{ $asset->serial_number ?? '—' }}</td>
                        <td>{{ $asset->department?->displayName() ?? '—' }}</td>
                        <td>{{ $asset->location ?? '—' }}</td>
                        <td>{{ $asset->assignee?->name ?? '—' }}</td>
                        <td>{{ \App\Support\Status::label('asset_condition', $asset->condition) }}</td>
                        <td>{{ \App\Support\Status::label('asset', $asset->status) }}</td>
                        <td class="nowrap">{{ fmt_date($asset->warranty_expiry) }}</td>
                        <td class="num text-right nowrap">{{ $asset->purchase_cost !== null ? money($asset->purchase_cost, $asset->currency) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @empty
        <div class="card"><x-empty icon="box" /></div>
    @endforelse
</x-layouts.app>
