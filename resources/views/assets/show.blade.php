@php
    $canUpdate = auth()->user()->can('update', $asset);
    $inService = $asset->isInService();
@endphp
<x-layouts.app :title="$asset->asset_tag.' — '.$asset->name">
    <x-page-header :title="$asset->name" :breadcrumbs="[__('Assets') => route('assets.index')]">
        <x-slot:meta>
            <p><span class="mono">{{ $asset->asset_tag }}</span> · <x-status domain="asset" :value="$asset->status" /> <x-status domain="asset_condition" :value="$asset->condition" /></p>
        </x-slot:meta>
        <x-slot:actions>
            @if ($canUpdate)
                <a href="{{ route('assets.edit', $asset) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endif
            @can('delete', $asset)
                <x-delete-button :action="route('assets.destroy', $asset)" :label="__('Delete')" class="btn btn-danger"
                    :confirm="__('Delete this asset from the register? Its history is kept in the audit log.')" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Asset details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Asset tag') }}</dt><dd class="mono">{{ $asset->asset_tag }}</dd>
                        <dt>{{ __('Category') }}</dt><dd>{{ $asset->category?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Manufacturer') }}</dt><dd>{{ $asset->manufacturer ?? '—' }}</dd>
                        <dt>{{ __('Model') }}</dt><dd>{{ $asset->model ?? '—' }}</dd>
                        <dt>{{ __('Serial number') }}</dt><dd class="mono">{{ $asset->serial_number ?? '—' }}</dd>
                        <dt>{{ __('Supplier') }}</dt><dd>@if ($asset->supplier)<a href="{{ route('suppliers.show', $asset->supplier) }}">{{ $asset->supplier->name }}</a>@else — @endif</dd>
                        <dt>{{ __('Purchase date') }}</dt><dd>{{ fmt_date($asset->purchase_date) }}</dd>
                        <dt>{{ __('Purchase cost') }}</dt><dd>{{ $asset->purchase_cost !== null ? money($asset->purchase_cost, $asset->currency) : '—' }}</dd>
                        <dt>{{ __('Warranty expiry') }}</dt><dd>@include('assets.partials.warranty', ['asset' => $asset])</dd>
                        <dt>{{ __('Department') }}</dt><dd>{{ $asset->department?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Location') }}</dt><dd>{{ $asset->location ?? '—' }}</dd>
                        <dt>{{ __('Assigned to') }}</dt><dd>{{ $asset->assignee?->name ?? '—' }}</dd>
                        <dt>{{ __('Registered') }}</dt><dd>{{ fmt_date($asset->created_at, true) }} @if ($asset->creator)<span class="muted">· {{ $asset->creator->name }}</span>@endif</dd>
                        @if ($asset->notes)
                            <dt>{{ __('Notes') }}</dt><dd class="prose">{{ $asset->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card" id="specifications">
                <div class="card__head"><h2>{{ __('Technical specifications') }}</h2></div>
                @if (empty($asset->specifications))
                    <x-empty icon="database" :message="__('No technical specifications recorded.')" />
                @else
                    <div class="card__body">
                        <dl class="dl">
                            @foreach ($asset->specifications as $key => $value)
                                <dt>{{ $key }}</dt><dd>{{ is_scalar($value) ? $value : json_encode($value) }}</dd>
                            @endforeach
                        </dl>
                    </div>
                @endif
            </div>

            @include('assets.partials.assignments')
            @include('assets.partials.maintenance')
            @include('assets.partials.disposals')

            @if ($latestDisposal)
                <x-approval-panel :model="$latestDisposal" :title="$latestDisposal->action === 'retire' ? __('Retirement approval') : __('Disposal approval')" />
            @endif

            <x-attachments :model="$asset" :can-upload="$canUpdate" />
            <x-audit-trail :model="$asset" />
        </div>

        <div class="stack">
            @include('assets.partials.custody')
            @if ($canUpdate && $inService && ! $openDisposal)
                @include('assets.partials.disposal-form')
            @endif
        </div>
    </div>
</x-layouts.app>
