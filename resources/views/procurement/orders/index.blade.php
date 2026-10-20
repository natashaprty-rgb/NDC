<x-layouts.app :title="__('Purchase orders')">
    <x-page-header :title="__('Purchase orders')" :subtitle="__('Orders and contracts issued to suppliers, with delivery status.')" :breadcrumbs="[__('Purchase requests') => route('procurement.index')]" />
    <div class="card">
        <x-filters>
            <x-search-field :placeholder="__('PO number, contract or request')" />
            <x-filter-select name="status" :label="__('Status')" :options="\App\Support\Status::options('purchase_order')" />
            <x-filter-select name="supplier" :label="__('Supplier')" :options="$suppliers" />
            <x-filter-select name="overdue" :label="__('Delivery')" :options="['1' => __('Overdue deliveries')]" />
        </x-filters>
        @if ($orders->isEmpty())
            <x-empty icon="file" />
        @else
            <div class="table-wrap"><table class="table">
                <thead><tr>
                    <th><x-sort-link column="po_number" :label="__('PO number')" /></th>
                    <th>{{ __('Purchase request') }}</th>
                    <th>{{ __('Supplier') }}</th>
                    <th><x-sort-link column="order_date" :label="__('Order date')" /></th>
                    <th><x-sort-link column="expected_delivery_date" :label="__('Expected delivery')" /></th>
                    <th class="text-right"><x-sort-link column="total_amount" :label="__('Amount')" /></th>
                    <th><x-sort-link column="status" :label="__('Status')" /></th>
                </tr></thead>
                <tbody>
                @foreach ($orders as $po)
                    @php $late = in_array($po->status, ['issued', 'partially_delivered'], true) && $po->expected_delivery_date?->lt(today()); @endphp
                    <tr>
                        <td class="nowrap"><a href="{{ route('purchase-orders.show', $po) }}">{{ $po->po_number }}</a></td>
                        <td>@if ($po->request)<a href="{{ route('procurement.show', $po->request) }}">{{ $po->request->reference_number }}</a><span class="sub">{{ $po->request->title }}</span>@else — @endif</td>
                        <td>{{ $po->supplier?->name ?? '—' }}</td>
                        <td class="nowrap">{{ fmt_date($po->order_date) }}</td>
                        <td class="nowrap">{{ fmt_date($po->expected_delivery_date) }} @if ($late)<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</td>
                        <td class="num text-right nowrap">{{ money($po->total_amount, $po->currency) }}</td>
                        <td><x-status domain="purchase_order" :value="$po->status" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
        {{ $orders->links() }}
    </div>
</x-layouts.app>
