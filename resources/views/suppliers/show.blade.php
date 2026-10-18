<x-layout :title="$supplier->name" :subtitle="$supplier->code"
          :breadcrumbs="[__('suppliers.title') => route('suppliers.index'), $supplier->name]">
    <x-slot:actions>
        @can('create', \App\Models\SupplierContract::class)
            <a href="{{ route('contracts.create', $supplier) }}" class="btn"><x-icon name="plus" width="16" height="16" /> {{ __('suppliers.new_contract') }}</a>
        @endcan
        @can('update', $supplier)
            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('delete', $supplier)
            <x-delete-button :action="route('suppliers.destroy', $supplier)" :label="__('common.archive')" :message="__('common.confirm_archive')" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('suppliers.contracts')" :flush="true">
                @if($contracts->isEmpty())
                    <x-empty :message="__('suppliers.no_contracts')" icon="file" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('suppliers.contract_title') }}</th><th>{{ __('suppliers.end_date') }}</th><th class="num">{{ __('suppliers.contract_value') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                        <tbody>@foreach($contracts as $contract)
                            <tr>
                                <td class="nowrap"><a href="{{ route('contracts.show', $contract) }}">{{ $contract->reference }}</a></td>
                                <td>{{ $contract->title }}</td>
                                <td class="nowrap">{{ fmt_date($contract->end_date) }}</td>
                                <td class="num">{{ $contract->contract_value !== null ? money($contract->contract_value, $contract->currency) : '—' }}</td>
                                <td><x-badge :value="$contract->status" /></td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </x-card>

            <x-card :title="__('suppliers.purchase_orders')" :flush="true">
                @if($orders->isEmpty())
                    <x-empty :message="__('suppliers.no_orders')" icon="cart" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('procurement.order_date') }}</th><th class="num">{{ __('common.total') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                        <tbody>@foreach($orders as $po)
                            <tr>
                                <td class="nowrap">@if(auth()->user()->can('view', $po))<a href="{{ route('purchase-orders.show', $po) }}">{{ $po->reference }}</a>@else{{ $po->reference }}@endif</td>
                                <td class="nowrap">{{ fmt_date($po->order_date) }}</td>
                                <td class="num">{{ money($po->total_amount, $po->currency) }}</td>
                                <td><x-badge :value="$po->status" /></td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </x-card>

            <x-card :title="__('suppliers.quotations')" :flush="true">
                @if($quotations->isEmpty())
                    <x-empty :message="__('suppliers.no_quotations')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('procurement.purchase_request') }}</th><th>{{ __('procurement.quotation_date') }}</th><th class="num">{{ __('procurement.quoted_total') }}</th><th>{{ __('procurement.selected') }}</th></tr></thead>
                        <tbody>@foreach($quotations as $q)
                            <tr>
                                <td class="nowrap">{{ $q->purchaseRequest?->reference ?? '—' }}</td>
                                <td class="nowrap">{{ fmt_date($q->quotation_date) }}</td>
                                <td class="num">{{ money($q->total_amount, $q->purchaseRequest?->currency) }}</td>
                                <td>{{ $q->is_selected ? __('common.yes') : __('common.no') }}</td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </x-card>

            <x-attachments :model="$supplier" :categories="['general', 'contract', 'report']" />
        </div>
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$supplier->status" /></dd>
                    <dt>{{ __('suppliers.registration_number') }}</dt><dd>{{ $supplier->registration_number ?? '—' }}</dd>
                    <dt>{{ __('suppliers.tax_number') }}</dt><dd>{{ $supplier->tax_number ?? '—' }}</dd>
                    <dt>{{ __('suppliers.contact_person') }}</dt><dd>{{ $supplier->contact_person ?? '—' }}</dd>
                    <dt>{{ __('suppliers.email') }}</dt><dd>{{ $supplier->email ?? '—' }}</dd>
                    <dt>{{ __('suppliers.phone') }}</dt><dd>{{ $supplier->phone ?? '—' }}</dd>
                    <dt>{{ __('suppliers.address') }}</dt><dd>{{ $supplier->address ?? '—' }}</dd>
                    <dt>{{ __('suppliers.country') }}</dt><dd>{{ $supplier->country ?? '—' }}</dd>
                    <dt>{{ __('suppliers.services') }}</dt><dd>{{ $supplier->services ?? '—' }}</dd>
                </dl>
                @if($supplier->notes)<p class="prose mt-2 mb-0">{{ $supplier->notes }}</p>@endif
            </x-card>
            <x-history :model="$supplier" />
        </div>
    </div>
</x-layout>
