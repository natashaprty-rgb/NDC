<x-layout :title="__('suppliers.contracts')" :breadcrumbs="[__('suppliers.title') => route('suppliers.index'), __('suppliers.contracts')]">
    <div class="tabs">
        <a href="{{ route('contracts.index') }}" class="{{ request()->boolean('expiring') ? '' : 'is-active' }}">{{ __('common.all') }}</a>
        <a href="{{ route('contracts.index', ['expiring' => 1]) }}" class="{{ request()->boolean('expiring') ? 'is-active' : '' }}">{{ __('suppliers.expiring_within', ['days' => $days]) }}</a>
    </div>
    <section class="card">
        <x-filter-bar :action="route('contracts.index')">
            @if(request()->boolean('expiring'))<input type="hidden" name="expiring" value="1">@endif
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\SupplierContract::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
        </x-filter-bar>
        @if($contracts->isEmpty())
            <x-empty :message="__('suppliers.no_contracts')" icon="file" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('suppliers.contract_title') }}</th><th>{{ __('suppliers.supplier') }}</th><th>{{ __('suppliers.start_date') }}</th><th>{{ __('suppliers.end_date') }}</th><th class="num">{{ __('suppliers.contract_value') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                <tbody>@foreach($contracts as $contract)
                    <tr>
                        <td class="nowrap"><a href="{{ route('contracts.show', $contract) }}">{{ $contract->reference }}</a></td>
                        <td>{{ $contract->title }}</td>
                        <td><a href="{{ route('suppliers.show', $contract->supplier) }}">{{ $contract->supplier?->name }}</a></td>
                        <td class="nowrap">{{ fmt_date($contract->start_date) }}</td>
                        <td class="nowrap">{{ fmt_date($contract->end_date) }}</td>
                        <td class="num">{{ $contract->contract_value !== null ? money($contract->contract_value, $contract->currency) : '—' }}</td>
                        <td><x-badge :value="$contract->status" /></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $contracts->links() }}
        @endif
    </section>
</x-layout>
