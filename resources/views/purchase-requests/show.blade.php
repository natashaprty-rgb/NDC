@php $user = auth()->user(); @endphp
<x-layout :title="$pr->title" :subtitle="$pr->reference"
          :breadcrumbs="[__('procurement.title') => route('purchase-requests.index'), $pr->reference]">
    <x-slot:actions>
        @can('update', $pr)
            <a href="{{ route('purchase-requests.edit', $pr) }}" class="btn"><x-icon name="edit" width="16" height="16" /> {{ __('common.edit') }}</a>
        @endcan
        @can('submit', $pr)
            <form method="POST" action="{{ route('purchase-requests.submit', $pr) }}" class="inline-form" data-confirm="{{ __('procurement.confirm_submit') }}">@csrf<button class="btn btn--primary" type="submit">{{ __('procurement.submit_for_approval') }}</button></form>
        @endcan
        @if($canOrder && in_array($pr->status, ['approved', 'ordered', 'partially_received'], true))
            <a href="{{ route('purchase-orders.create', $pr) }}" class="btn btn--primary"><x-icon name="cart" width="16" height="16" /> {{ __('procurement.raise_po') }}</a>
        @endif
        @can('delete', $pr)
            <x-delete-button :action="route('purchase-requests.destroy', $pr)" class="btn btn--danger" />
        @endcan
    </x-slot:actions>

    <div class="layout-main-side">
        <div>
            <x-card :title="__('procurement.items')" :flush="true">
                <div class="table-wrap"><table class="table">
                    <thead><tr><th>{{ __('procurement.item_description') }}</th><th class="num">{{ __('procurement.quantity') }}</th><th>{{ __('procurement.unit') }}</th><th class="num">{{ __('procurement.unit_cost') }}</th><th class="num">{{ __('procurement.line_total') }}</th></tr></thead>
                    <tbody>@foreach($pr->items as $item)
                        <tr><td>{{ $item->description }}</td><td class="num">{{ $item->quantity }}</td><td>{{ $item->unit }}</td><td class="num">{{ money($item->estimated_unit_cost) }}</td><td class="num">{{ money($item->line_total) }}</td></tr>
                    @endforeach</tbody>
                    <tfoot><tr><td colspan="4" class="text-right">{{ __('procurement.estimated_total') }}</td><td class="num">{{ money($pr->estimated_total, $pr->currency) }}</td></tr></tfoot>
                </table></div>
            </x-card>

            @can('approve', $pr)
                <x-card :title="__('procurement.decision')">
                    <form method="POST" action="{{ route('purchase-requests.approve', $pr) }}">
                        @csrf
                        <x-field.textarea name="decision_note" :label="__('common.decision_note')" rows="2" />
                        <div class="form-actions">
                            <button type="submit" class="btn btn--danger" formaction="{{ route('purchase-requests.reject', $pr) }}">{{ __('common.reject') }}</button>
                            <button type="submit" class="btn btn--success">{{ __('common.approve') }}</button>
                        </div>
                    </form>
                    <p class="small muted mb-0">{{ __('procurement.reject_note_required') }}</p>
                </x-card>
            @endcan

            <section class="card" id="quotations">
                <div class="card__header"><h2>{{ __('procurement.quotations') }}</h2></div>
                <div class="card__body card__body--flush">
                    @if($pr->quotations->isEmpty())
                        <x-empty :message="__('procurement.no_quotations')" />
                    @else
                        <div class="table-wrap"><table class="table">
                            <thead><tr><th>{{ __('procurement.supplier') }}</th><th>{{ __('procurement.supplier_reference') }}</th><th>{{ __('procurement.valid_until') }}</th><th class="num">{{ __('procurement.quoted_total') }}</th><th></th></tr></thead>
                            <tbody>@foreach($pr->quotations as $q)
                                <tr>
                                    <td>{{ $q->supplier?->name }}@if($q->notes)<br><span class="small muted">{{ $q->notes }}</span>@endif
                                        @foreach($q->attachments as $att)<br><a class="small" href="{{ route('attachments.download', $att) }}">{{ $att->original_name }}</a>@endforeach</td>
                                    <td>{{ $q->supplier_reference ?? '—' }}</td>
                                    <td class="nowrap">{{ fmt_date($q->valid_until) }}</td>
                                    <td class="num">{{ money($q->total_amount, $pr->currency) }}</td>
                                    <td class="text-right nowrap">
                                        @if($q->is_selected)
                                            <x-badge value="selected" :label="__('procurement.selected')" />
                                        @elseif($canOrder)
                                            <form method="POST" action="{{ route('quotations.select', $q) }}" class="inline-form">@csrf<button class="btn btn--sm" type="submit">{{ __('procurement.select') }}</button></form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach</tbody>
                        </table></div>
                    @endif
                </div>
                @if($canOrder)
                    <div class="card__footer">
                        <form method="POST" action="{{ route('purchase-requests.quotations.store', $pr) }}" enctype="multipart/form-data" class="form-grid">
                            @csrf
                            <x-field.select name="supplier_id" :label="__('procurement.supplier')" :options="$suppliers" :required="true" />
                            <x-field.input name="supplier_reference" :label="__('procurement.supplier_reference')" maxlength="100" />
                            <x-field.input name="quotation_date" type="date" :label="__('procurement.quotation_date')" />
                            <x-field.input name="valid_until" type="date" :label="__('procurement.valid_until')" />
                            <x-field.input name="total_amount" :label="__('procurement.quoted_total')" :required="true" inputmode="decimal" />
                            <x-field.file name="file" />
                            <x-field.textarea name="notes" :label="__('common.notes')" rows="2" class="span-2" />
                            <div class="span-2 text-right"><button type="submit" class="btn btn--primary btn--sm">{{ __('procurement.add_quotation') }}</button></div>
                        </form>
                    </div>
                @endif
            </section>

            @if($pr->purchaseOrders->isNotEmpty())
                <x-card :title="__('procurement.purchase_orders')" :flush="true">
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('procurement.supplier') }}</th><th>{{ __('procurement.order_date') }}</th><th class="num">{{ __('common.total') }}</th><th>{{ __('common.status') }}</th></tr></thead>
                        <tbody>@foreach($pr->purchaseOrders as $po)
                            <tr>
                                <td class="nowrap">@can('view', $po)<a href="{{ route('purchase-orders.show', $po) }}">{{ $po->reference }}</a>@else{{ $po->reference }}@endcan</td>
                                <td>{{ $po->supplier?->name }}</td>
                                <td class="nowrap">{{ fmt_date($po->order_date) }}</td>
                                <td class="num">{{ money($po->total_amount, $po->currency) }}</td>
                                <td><x-badge :value="$po->status" /></td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                </x-card>
            @endif

            <x-attachments :model="$pr" :categories="['general', 'quotation', 'report']" />
        </div>
        <div>
            <x-card :title="__('common.details')">
                <dl class="dl">
                    <dt>{{ __('common.status') }}</dt><dd><x-badge :value="$pr->status" /></dd>
                    <dt>{{ __('procurement.requested_by') }}</dt><dd>{{ $pr->requester?->name }}</dd>
                    <dt>{{ __('common.department') }}</dt><dd>{{ $pr->department?->displayName() ?? '—' }}</dd>
                    <dt>{{ __('common.project') }}</dt><dd>{{ $pr->project?->name ?? '—' }}</dd>
                    <dt>{{ __('procurement.budget') }}</dt><dd>{{ $pr->budget ? $pr->budget->reference.' — '.$pr->budget->title : '—' }}</dd>
                    <dt>{{ __('procurement.needed_by') }}</dt><dd>{{ fmt_date($pr->needed_by) }}</dd>
                    <dt>{{ __('procurement.submitted_at') }}</dt><dd>{{ fmt_datetime($pr->submitted_at) }}</dd>
                    <dt>{{ __('procurement.decided_by') }}</dt><dd>{{ $pr->decidedBy?->name ?? '—' }} @if($pr->decided_at)<br><span class="small muted">{{ fmt_datetime($pr->decided_at) }}</span>@endif</dd>
                    @if($pr->decision_note)<dt>{{ __('common.decision_note') }}</dt><dd class="prose">{{ $pr->decision_note }}</dd>@endif
                </dl>
                @if($pr->justification)<h3 class="mt-2">{{ __('procurement.justification') }}</h3><p class="prose mb-0">{{ $pr->justification }}</p>@endif
            </x-card>

            @can('cancel', $pr)
                <x-card :title="__('procurement.cancel_request')">
                    <form method="POST" action="{{ route('purchase-requests.cancel', $pr) }}" data-confirm="{{ __('procurement.confirm_cancel') }}">
                        @csrf
                        <x-field.textarea name="reason" :label="__('common.reason')" rows="2" :required="true" />
                        <div class="form-actions"><button type="submit" class="btn btn--danger btn--sm">{{ __('procurement.cancel_request') }}</button></div>
                    </form>
                </x-card>
            @endcan

            <x-history :model="$pr" />
        </div>
    </div>
</x-layout>
