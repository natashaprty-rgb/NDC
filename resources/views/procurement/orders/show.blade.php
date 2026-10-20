@php
    $user = auth()->user();
    $canReceive = $user->can('receive', $order);
    $openItems = $pr->items->filter(fn ($i) => $i->outstandingQuantity() > 0);
@endphp
<x-layouts.app :title="$order->po_number">
    <x-page-header :title="__('Purchase order :number', ['number' => $order->po_number])" :subtitle="$pr->title"
        :breadcrumbs="[__('Purchase requests') => route('procurement.index'), $pr->reference_number => route('procurement.show', $pr)]">
        <x-slot:meta><p><x-status domain="purchase_order" :value="$order->status" /></p></x-slot:meta>
        <x-slot:actions>
            <button type="button" class="btn" data-print><x-icon name="printer" /> {{ __('Print') }}</button>
            @can('update', $order)
                <a href="{{ route('purchase-orders.edit', $order) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Order details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('PO number') }}</dt><dd class="mono">{{ $order->po_number }}</dd>
                        <dt>{{ __('Supplier') }}</dt><dd>@if ($order->supplier)<a href="{{ route('suppliers.show', $order->supplier) }}">{{ $order->supplier->name }}</a>@else — @endif</dd>
                        <dt>{{ __('Purchase request') }}</dt><dd><a href="{{ route('procurement.show', $pr) }}">{{ $pr->reference_number }}</a> · {{ $pr->department?->displayName() }}</dd>
                        <dt>{{ __('Quotation') }}</dt><dd>{{ $order->quotation ? ($order->quotation->reference ?: '#'.$order->quotation->id).' — '.money($order->quotation->amount, $order->quotation->currency) : '—' }}</dd>
                        <dt>{{ __('Order date') }}</dt><dd>{{ fmt_date($order->order_date) }}</dd>
                        <dt>{{ __('Expected delivery') }}</dt><dd>{{ fmt_date($order->expected_delivery_date) }}
                            @if ($order->canReceiveGoods() && $order->expected_delivery_date?->lt(today()))<span class="badge badge--danger">{{ __('Overdue') }}</span>@endif</dd>
                        <dt>{{ __('Delivered on') }}</dt><dd>{{ fmt_date($order->delivered_on) }}</dd>
                        <dt>{{ __('Order amount') }}</dt><dd><strong>{{ money($order->total_amount, $order->currency) }}</strong></dd>
                        <dt>{{ __('Contract reference') }}</dt><dd>{{ $order->contract_reference ?? '—' }}</dd>
                        <dt>{{ __('Contract end date') }}</dt><dd>{{ fmt_date($order->contract_end_date) }}</dd>
                        <dt>{{ __('Issued by') }}</dt><dd>{{ $order->creator?->name ?? '—' }} · {{ fmt_date($order->created_at, true) }}</dd>
                        @if ($order->terms)<dt>{{ __('Terms and conditions') }}</dt><dd class="prose">{{ $order->terms }}</dd>@endif
                    </dl>
                </div>
            </div>

            <div class="card" id="items">
                <div class="card__head"><h2>{{ __('Items') }}</h2></div>
                @include('procurement.partials.items-table', ['pr' => $pr])
            </div>

            <div class="card" id="receipts">
                <div class="card__head"><h2>{{ __('Deliveries') }}</h2></div>
                @if ($order->receipts->isEmpty())
                    <x-empty icon="truck" :message="__('No goods have been received against this order.')" />
                @else
                    <ul class="list">
                        @foreach ($order->receipts as $receipt)
                            <li>
                                <div><strong>{{ fmt_date($receipt->received_on) }}</strong>
                                    <span class="muted small">· {{ $receipt->receiver?->name ?? '—' }}@if ($receipt->delivery_note_number) · {{ __('Delivery note') }} {{ $receipt->delivery_note_number }}@endif</span></div>
                                <div class="small">
                                    {{ $receipt->items->map(fn ($ri) => ((float) $ri->quantity).' × '.($ri->requestItem?->description ?? '—'))->join('; ') }}
                                </div>
                                @if ($receipt->notes)<div class="small prose muted">{{ $receipt->notes }}</div>@endif
                                @foreach ($receipt->attachments as $file)
                                    <div class="small"><x-icon name="paperclip" /> <a href="{{ route('attachments.download', $file) }}">{{ $file->original_name }}</a></div>
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($canReceive && $openItems->isNotEmpty())
                    <div class="card__foot">
                        <form method="POST" action="{{ route('purchase-orders.receipts.store', $order) }}" enctype="multipart/form-data">
                            @csrf
                            <h3>{{ __('Record goods received') }}</h3>
                            @error('quantities')<div class="field-error">{{ $message }}</div>@enderror
                            <div class="table-wrap"><table class="table">
                                <thead><tr><th>{{ __('Item') }}</th><th class="text-right">{{ __('Outstanding') }}</th><th>{{ __('Quantity received now') }}</th></tr></thead>
                                <tbody>
                                @foreach ($openItems as $item)
                                    <tr>
                                        <td><label for="qty_{{ $item->id }}">{{ $item->description }}</label></td>
                                        <td class="num text-right">{{ $item->outstandingQuantity() }} {{ $item->unit }}</td>
                                        <td>
                                            <input class="input" type="number" step="0.01" min="0" max="{{ $item->outstandingQuantity() }}" id="qty_{{ $item->id }}"
                                                name="quantities[{{ $item->id }}]" value="{{ old('quantities.'.$item->id) }}" @if ($errors->has('quantities.'.$item->id)) aria-invalid="true" @endif>
                                            @error('quantities.'.$item->id)<div class="field-error">{{ $message }}</div>@enderror
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table></div>
                            <div class="form-grid" style="margin-top:.75rem">
                                <x-form.input name="received_on" type="date" :label="__('Date received')" :value="today()->toDateString()" required />
                                <x-form.input name="delivery_note_number" :label="__('Delivery note number')" maxlength="255" />
                                <x-form.textarea name="notes" :label="__('Notes')" rows="2" class="span-2" />
                                <x-form.input type="file" name="file" :label="__('Delivery note (scan)')" class="span-2" />
                            </div>
                            <button class="btn btn-primary">{{ __('Record delivery') }}</button>
                        </form>
                    </div>
                @endif
            </div>

            <x-attachments :model="$order" collection="*" :can-upload="$user->can('update', $pr) && $order->status !== 'cancelled'" :title="__('Order and contract documents')" />
            <x-audit-trail :model="$order" />
        </div>

        <div class="stack">
            @if ($order->supplier)
                <div class="card">
                    <div class="card__head"><h2>{{ __('Supplier performance') }}</h2></div>
                    <div class="card__body">
                        @forelse ($order->evaluations as $evaluation)
                            <p class="mb-0"><strong>{{ number_format($evaluation->score(), 1) }} / 5</strong>
                                <span class="small muted">· {{ $evaluation->evaluator?->name }} · {{ fmt_date($evaluation->created_at) }}</span></p>
                        @empty
                            <p class="muted">{{ __('This order has not been evaluated.') }}</p>
                        @endforelse
                        @can('update', $order->supplier)
                            @if (in_array($order->status, ['partially_delivered', 'delivered'], true))
                                @include('suppliers.partials.evaluation-form', ['supplier' => $order->supplier, 'orderOptions' => [$order->id => $order->po_number], 'selectedOrder' => $order->id])
                            @endif
                        @endcan
                    </div>
                </div>
            @endif

            @can('cancel', $order)
                <div class="card">
                    <div class="card__body">
                        <details @if ($errors->has('reason')) open @endif>
                            <summary>{{ __('Cancel this purchase order') }}</summary>
                            <form method="POST" action="{{ route('purchase-orders.cancel', $order) }}" style="margin-top:.75rem" data-confirm="{{ __('Cancel this purchase order?') }}">
                                @csrf
                                <x-form.textarea name="reason" id="po_cancel_reason" :label="__('Reason')" rows="2" required />
                                <button class="btn btn-danger">{{ __('Cancel order') }}</button>
                            </form>
                        </details>
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-layouts.app>
