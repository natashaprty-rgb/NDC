<x-layout :title="$po->reference" :subtitle="$po->supplier?->name"
          :breadcrumbs="[__('procurement.title') => route('purchase-requests.index'), $po->purchaseRequest->reference => route('purchase-requests.show', $po->purchaseRequest), $po->reference]">
    <x-slot:actions>
        @can('cancel', $po)
            <form method="POST" action="{{ route('purchase-orders.cancel', $po) }}" class="inline-form" data-confirm="{{ __('procurement.confirm_cancel_po') }}">@csrf<button class="btn btn--danger" type="submit">{{ __('procurement.cancel_po') }}</button></form>
        @endcan
    </x-slot:actions>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('procurement.items')" :flush="true">
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('procurement.item_description') }}</th><th class="num">{{ __('procurement.ordered') }}</th><th class="num">{{ __('procurement.received') }}</th><th class="num">{{ __('procurement.outstanding') }}</th><th class="num">{{ __('procurement.unit_price') }}</th><th class="num">{{ __('procurement.line_total') }}</th></tr></thead>
                    <tbody>@foreach($po->items as $item)
                        <tr>
                            <td>{{ $item->description }} <span class="small muted">({{ $item->unit }})</span></td>
                            <td class="num">{{ $item->quantity }}</td>
                            <td class="num">{{ $item->quantity_received }}</td>
                            <td class="num">{{ \App\Support\Money::fromCents(max(0, $item->outstandingHundredths())) }}</td>
                            <td class="num">{{ money($item->unit_price) }}</td>
                            <td class="num">{{ money($item->line_total) }}</td>
                        </tr>
                    @endforeach</tbody>
                    <tfoot><tr><td colspan="5" class="text-right">{{ __('common.total') }}</td><td class="num">{{ money($po->total_amount, $po->currency) }}</td></tr></tfoot>
                </table></div>
            </x-card>

            @can('receive', $po)
                <x-card :title="__('procurement.record_receipt')">
                    <form method="POST" action="{{ route('purchase-orders.receipts.store', $po) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="form-grid">
                            <x-field.input name="received_date" type="date" :label="__('procurement.received_date')" :value="today()" :required="true" />
                            <x-field.input name="delivery_note_number" :label="__('procurement.delivery_note_number')" maxlength="100" />
                        </div>
                        @error('items')<p class="error-text">{{ $message }}</p>@enderror
                        <div class="table-wrap mt-1"><table class="table">
                            <thead><tr><th>{{ __('procurement.item_description') }}</th><th class="num">{{ __('procurement.outstanding') }}</th><th style="width:130px">{{ __('procurement.quantity_received') }}</th><th>{{ __('procurement.condition_note') }}</th></tr></thead>
                            <tbody>@foreach($po->items as $i => $item)
                                <tr>
                                    <td>{{ $item->description }}<input type="hidden" name="items[{{ $i }}][purchase_order_item_id]" value="{{ $item->id }}"></td>
                                    <td class="num">{{ \App\Support\Money::fromCents(max(0, $item->outstandingHundredths())) }}</td>
                                    <td><input class="input @error("items.$i.quantity_received") is-invalid @enderror" name="items[{{ $i }}][quantity_received]" value="{{ old("items.$i.quantity_received", '0') }}" inputmode="decimal" aria-label="{{ __('procurement.quantity_received') }}">
                                        @error("items.$i.quantity_received")<span class="error">{{ $message }}</span>@enderror</td>
                                    <td><input class="input" name="items[{{ $i }}][condition_note]" maxlength="300" aria-label="{{ __('procurement.condition_note') }}"></td>
                                </tr>
                            @endforeach</tbody>
                        </table></div>
                        <div class="form-grid mt-1">
                            <x-field.file name="file" :label="__('procurement.delivery_note_file')" />
                            <x-field.textarea name="notes" :label="__('common.notes')" rows="2" />
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn--primary">{{ __('procurement.record_receipt') }}</button></div>
                    </form>
                </x-card>
            @endcan

            <x-card :title="__('procurement.receipts')" :flush="true">
                @if($po->receipts->isEmpty())
                    <x-empty :message="__('procurement.no_receipts')" icon="box" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('procurement.received_date') }}</th><th>{{ __('procurement.received_by') }}</th><th>{{ __('procurement.items') }}</th></tr></thead>
                        <tbody>@foreach($po->receipts as $receipt)
                            <tr>
                                <td class="nowrap">{{ $receipt->reference }}@if($receipt->delivery_note_number)<br><span class="small muted">{{ $receipt->delivery_note_number }}</span>@endif
                                    @foreach($receipt->attachments as $att)<br><a class="small" href="{{ route('attachments.download', $att) }}">{{ $att->original_name }}</a>@endforeach</td>
                                <td class="nowrap">{{ fmt_date($receipt->received_date) }}</td>
                                <td>{{ $receipt->receiver?->name }}</td>
                                <td class="small">@foreach($receipt->items as $ri){{ $ri->orderItem?->description }}: {{ $ri->quantity_received }}@if($ri->condition_note) ({{ $ri->condition_note }})@endif<br>@endforeach</td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </x-card>

            <x-attachments :model="$po" :categories="['general', 'invoice', 'delivery_note', 'contract']" />
        </div>
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$po->status" /></dd>
                    <dt>{{ __('procurement.supplier') }}</dt><dd>@if($po->supplier)<a href="{{ route('suppliers.show', $po->supplier) }}">{{ $po->supplier->name }}</a>@else — @endif</dd>
                    <dt>{{ __('procurement.purchase_request') }}</dt><dd><a href="{{ route('purchase-requests.show', $po->purchaseRequest) }}">{{ $po->purchaseRequest->reference }}</a></dd>
                    <dt>{{ __('procurement.requested_by') }}</dt><dd>{{ $po->purchaseRequest->requester?->name }}</dd>
                    <dt>{{ __('procurement.order_date') }}</dt><dd>{{ fmt_date($po->order_date) }}</dd>
                    <dt>{{ __('procurement.expected_delivery') }}</dt><dd>{{ fmt_date($po->expected_delivery_date) }}</dd>
                    <dt>{{ __('procurement.quotation') }}</dt><dd>{{ $po->quotation ? ($po->quotation->supplier_reference ?: '#'.$po->quotation->id) : '—' }}</dd>
                </dl>
                @if($po->terms)<h3 class="mt-2">{{ __('procurement.terms') }}</h3><p class="prose mb-0">{{ $po->terms }}</p>@endif
                <p class="small muted mt-2 mb-0">{{ __('procurement.no_payment_note') }}</p>
            </x-card>
            <x-history :model="$po" />
        </div>
    </div>
</x-layout>
