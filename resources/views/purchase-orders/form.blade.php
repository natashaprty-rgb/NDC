<x-layout :title="__('procurement.raise_po')" :subtitle="$pr->reference.' — '.$pr->title"
          :breadcrumbs="[__('procurement.title') => route('purchase-requests.index'), $pr->reference => route('purchase-requests.show', $pr), __('procurement.raise_po')]">
    <form method="POST" action="{{ route('purchase-orders.store', $pr) }}">
        @csrf
        <x-card :title="__('common.details')">
            <div class="form-grid">
                <x-field.select name="supplier_id" :label="__('procurement.supplier')" :options="$suppliers" :value="$selected?->supplier_id" :required="true" />
                <x-field.select name="quotation_id" :label="__('procurement.quotation')" :options="$quotations" :value="$selected?->id" :placeholder="__('common.none')" />
                <x-field.input name="order_date" type="date" :label="__('procurement.order_date')" :value="today()" :required="true" />
                <x-field.input name="expected_delivery_date" type="date" :label="__('procurement.expected_delivery')" />
                <x-field.textarea name="terms" :label="__('procurement.terms')" rows="3" class="span-2" />
            </div>
        </x-card>
        <section class="card" data-repeater>
            <div class="card__header"><h2>{{ __('procurement.items') }}</h2></div>
            <div class="card__body card__body--flush">
                @error('items')<p class="error-text" style="padding:.6rem 1.1rem 0">{{ $message }}</p>@enderror
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('procurement.item_description') }}</th><th class="num">{{ __('procurement.remaining_to_order') }}</th><th style="width:130px">{{ __('procurement.quantity') }}</th><th style="width:150px">{{ __('procurement.unit_price') }}</th></tr></thead>
                    <tbody data-repeater-list>
                    @foreach($lines as $i => $line)
                        <tr data-repeater-row>
                            <td>{{ $line['item']->description }} <span class="small muted">({{ $line['item']->unit }})</span>
                                <input type="hidden" name="items[{{ $i }}][purchase_request_item_id]" value="{{ $line['item']->id }}"></td>
                            <td class="num">{{ $line['remaining'] }}</td>
                            <td><input class="input @error("items.$i.quantity") is-invalid @enderror" name="items[{{ $i }}][quantity]" value="{{ old("items.$i.quantity", $line['remaining']) }}" inputmode="decimal" data-qty aria-label="{{ __('procurement.quantity') }}">
                                @error("items.$i.quantity")<span class="error">{{ $message }}</span>@enderror</td>
                            <td><input class="input @error("items.$i.unit_price") is-invalid @enderror" name="items[{{ $i }}][unit_price]" value="{{ old("items.$i.unit_price", $line['item']->estimated_unit_cost) }}" inputmode="decimal" required data-price aria-label="{{ __('procurement.unit_price') }}">
                                @error("items.$i.unit_price")<span class="error">{{ $message }}</span>@enderror</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="3" class="text-right">{{ __('common.total') }} ({{ $pr->currency }})</td><td class="num"><span data-repeater-total>0.00</span></td></tr></tfoot>
                </table></div>
                <p class="small muted" style="padding:0 1.1rem .8rem">{{ __('procurement.po_items_help') }}</p>
            </div>
        </section>
        <div class="form-actions">
            <a href="{{ route('purchase-requests.show', $pr) }}" class="btn">{{ __('common.cancel') }}</a>
            <button type="submit" class="btn btn--primary">{{ __('procurement.issue_po') }}</button>
        </div>
    </form>
</x-layout>
