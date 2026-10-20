<x-layouts.app :title="__('Asset register')">
    <x-page-header :title="__('Asset register')" :subtitle="__('Computers, network and data center equipment, furniture and other NDC property.')">
        <x-slot:actions>
            <a href="{{ route('assets.reports.inventory', request()->query()) }}" class="btn"><x-icon name="printer" /> {{ __('Inventory report') }}</a>
            <a href="{{ route('assets.reports.allocation') }}" class="btn"><x-icon name="users" /> {{ __('Allocation report') }}</a>
            @can('create', \App\Models\Asset::class)
                <a href="{{ route('assets.create') }}" class="btn btn-primary"><x-icon name="plus" /> {{ __('Register asset') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-4" style="margin-bottom:1rem">
        @foreach (['available', 'assigned', 'under_maintenance', 'damaged'] as $state)
            <a class="card stat" href="{{ route('assets.index', ['status' => $state]) }}">
                <span class="stat__label">{{ \App\Support\Status::label('asset', $state) }}</span>
                <span class="stat__value">{{ (int) ($summary[$state] ?? 0) }}</span>
            </a>
        @endforeach
    </div>

    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('Tag, name, serial number, model or location')" />
            <x-filter-select name="category" :label="__('Category')" :options="$categories" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('asset')" />
            <x-filter-select name="condition" :label="__('Condition')" :options="\App\Support\Status::options('asset_condition')" />
            <x-filter-select name="department" :label="__('Department')" :options="$departments" />
            <x-filter-select name="assigned" :label="__('Assigned to')" :options="$users" />
            <x-filter-select name="warranty" :label="__('Warranty')" :options="$warrantyOptions" />
        </x-filters>
        @if ($assets->isEmpty())
            <x-empty icon="box" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="asset_tag" :label="__('Asset tag')" /></th>
                    <th><x-sort-link column="name" :label="__('Asset')" /></th>
                    <th>{{ __('Category') }}</th>
                    <th>{{ __('Assigned to') }}</th>
                    <th><x-sort-link column="condition" :label="__('Condition')" /></th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                    <th><x-sort-link column="warranty_expiry" :label="__('Warranty expiry')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($assets as $asset)
                    <tr>
                        <td class="nowrap"><a href="{{ route('assets.show', $asset) }}">{{ $asset->asset_tag }}</a></td>
                        <td class="title-cell"><a href="{{ route('assets.show', $asset) }}">{{ $asset->name }}</a>
                            <span class="sub">{{ collect([$asset->manufacturer, $asset->model, $asset->serial_number ? __('S/N :serial', ['serial' => $asset->serial_number]) : null])->filter()->join(' · ') ?: '—' }}</span></td>
                        <td>{{ $asset->category?->displayName() ?? '—' }}</td>
                        <td>{{ $asset->assignee?->name ?? '—' }}
                            <span class="sub">{{ collect([$asset->department?->displayName(), $asset->location])->filter()->join(' · ') }}</span></td>
                        <td><x-status domain="asset_condition" :value="$asset->condition" /></td>
                        <td><x-status domain="asset" :value="$asset->status" /></td>
                        <td class="nowrap">@include('assets.partials.warranty', ['asset' => $asset])</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $assets->links() }}
    </div>
</x-layouts.app>
