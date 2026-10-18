<x-layout :title="__('procurement.purchase_orders')" :breadcrumbs="[__('procurement.title') => route('purchase-requests.index'), __('procurement.purchase_orders')]">
    <section class="card">
        <x-filter-bar :action="route('purchase-orders.index')">
            <x-field.input name="q" :label="__('common.search')" :value="request('q')" class="field--search" />
            <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\PurchaseOrder::STATUSES)" :value="request('status')" :placeholder="__('common.all')" />
            <x-field.select name="supplier" :label="__('procurement.supplier')" :options="$suppliers" :value="request('supplier')" :placeholder="__('common.all')" />
        </x-filter-bar>
        @if($orders->isEmpty())
            <x-empty :message="__('procurement.no_orders')" icon="cart" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('procurement.supplier') }}</th><th>{{ __('procurement.purchase_request') }}</th><th>{{ __('procurement.order_date') }}</th><th>{{ __('procurement.expected_delivery') }}</th><th class="num">{{ __('common.total') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                <tbody>@foreach($orders as $po)
                    <tr>
                        <td class="nowrap"><a href="{{ route('purchase-orders.show', $po) }}">{{ $po->reference }}</a></td>
                        <td>{{ $po->supplier?->name }}</td>
                        <td class="nowrap"><a href="{{ route('purchase-requests.show', $po->purchase_request_id) }}">{{ $po->purchaseRequest?->reference }}</a></td>
                        <td class="nowrap">{{ fmt_date($po->order_date) }}</td>
                        <td class="nowrap">{{ fmt_date($po->expected_delivery_date) }}</td>
                        <td class="num">{{ money($po->total_amount, $po->currency) }}</td>
                        <td><x-badge :value="$po->status" /></td>
                    </tr>
                @endforeach</tbody>
            </table></div>
            {{ $orders->links() }}
        @endif
    </section>
</x-layout>
