<x-layout :title="__('assets.title')" :breadcrumbs="[__('assets.title')]">
    <x-slot:actions>
        @can('create', \App\Models\Asset::class)
            <a href="{{ route('assets.create') }}" class="btn btn--primary"><x-icon name="plus" width="16" height="16" /> {{ __('assets.new') }}</a>
        @endcan
    </x-slot:actions>

    @if($totals->sum() > 0)
        <div class="grid grid-4 mb-1">
            @foreach(\App\Models\Asset::STATUSES as $status)
                @if($totals->get($status, 0) > 0)
                    <x-stat :label="status_label($status)" :value="$totals->get($status, 0)" :href="route('assets.index', ['status' => $status])" />
                @endif
            @endforeach
        </div>
    @endif

    <section class="card mt-2">
        <x-filter-bar :action="route('assets.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" :placeholder="__('assets.search_hint')" />
            <x-field.select name="category" :label="__('assets.category')" :options="$categories" :value="request('category')" :placeholder="__('common.all')" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Asset::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="condition" :label="__('assets.condition')" :options="enum_options(\App\Models\Asset::CONDITIONS)" :value="request('condition')" :placeholder="__('common.all')" />
            <x-field.select name="location" :label="__('assets.location')" :options="$locations" :value="request('location')" :placeholder="__('common.all')" />
            <x-field.select name="department" :label="__('common.department')" :options="$departments" :value="request('department')" :placeholder="__('common.all')" />
            <x-field.select name="staff" :label="__('assets.assigned_staff')" :options="$staffList" :value="request('staff')" :placeholder="__('common.all')" />
            <x-field.select name="warranty_expiring" :label="__('assets.warranty_expiring')" :options="['1' => __('common.yes')]" :value="request('warranty_expiring')" :placeholder="__('common.all')" />
        </x-filter-bar>

        @if($assets->isEmpty())
            <x-empty :message="request()->except('page') ? __('common.no_records') : __('assets.empty')" icon="box" />
        @else
            <div class="table-wrap">
                <table class="table">
                    <thead><tr>
                        <th>{{ __('assets.asset_tag') }}</th><th>{{ __('assets.name') }}</th><th>{{ __('assets.category') }}</th>
                        <th>{{ __('assets.assigned_staff') }}</th><th>{{ __('assets.location') }}</th><th>{{ __('assets.condition') }}</th><th>{{ __('common.status') }}</th>
                    </tr></thead>
                    <tbody>
                    @foreach($assets as $asset)
                        <tr>
                            <td class="nowrap">{{ $asset->asset_tag }}</td>
                            <td><a href="{{ route('assets.show', $asset) }}">{{ $asset->name }}</a>@if($asset->serial_number)<br><span class="small muted">{{ __('assets.serial_short') }} {{ $asset->serial_number }}</span>@endif</td>
                            <td>{{ $asset->category?->displayName() ?? '—' }}</td>
                            <td>{{ $asset->assignedStaff?->full_name ?? '—' }}</td>
                            <td>{{ $asset->location?->name ?? '—' }}</td>
                            <td><x-badge :value="$asset->condition" /></td>
                            <td><x-badge :value="$asset->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $assets->links() }}
        @endif
    </section>
</x-layout>
