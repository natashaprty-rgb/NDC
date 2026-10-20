@php $canManage = auth()->user()->can('update', $supplier); @endphp
<x-layouts.app :title="$supplier->name">
    <x-page-header :title="$supplier->name" :breadcrumbs="[__('Suppliers') => route('suppliers.index')]">
        <x-slot:meta><p><x-status domain="supplier_type" :value="$supplier->type" /> <x-status domain="supplier" :value="$supplier->status" /></p></x-slot:meta>
        <x-slot:actions>
            @if ($canManage)
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn"><x-icon name="edit" /> {{ __('Edit') }}</a>
            @endif
            @can('delete', $supplier)
                <x-delete-button :action="route('suppliers.destroy', $supplier)" class="btn btn-danger" :confirm="__('Delete this supplier?')" />
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-main">
        <div class="stack">
            <div class="card">
                <div class="card__head"><h2>{{ __('Supplier details') }}</h2></div>
                <div class="card__body">
                    <dl class="dl">
                        <dt>{{ __('Category') }}</dt><dd>{{ $supplier->category?->displayName() ?? '—' }}</dd>
                        <dt>{{ __('Business registration number') }}</dt><dd>{{ $supplier->registration_number ?? '—' }}</dd>
                        <dt>{{ __('Tax identification number') }}</dt><dd>{{ $supplier->tax_number ?? '—' }}</dd>
                        <dt>{{ __('Contact person') }}</dt><dd>{{ $supplier->contact_person ?? '—' }}</dd>
                        <dt>{{ __('Email address') }}</dt><dd>@if ($supplier->email)<a href="mailto:{{ $supplier->email }}">{{ $supplier->email }}</a>@else — @endif</dd>
                        <dt>{{ __('Phone') }}</dt><dd>{{ $supplier->phone ?? '—' }}</dd>
                        <dt>{{ __('Website') }}</dt><dd>@if ($supplier->website)<a href="{{ $supplier->website }}" rel="noopener noreferrer" target="_blank">{{ $supplier->website }}</a>@else — @endif</dd>
                        <dt>{{ __('Address') }}</dt><dd>{{ $supplier->address ?? '—' }}</dd>
                        <dt>{{ __('Registered') }}</dt><dd>{{ fmt_date($supplier->created_at) }}</dd>
                        @if ($supplier->notes)<dt>{{ __('Notes') }}</dt><dd class="prose">{{ $supplier->notes }}</dd>@endif
                    </dl>
                </div>
            </div>

            <div class="card" id="orders">
                <div class="card__head"><h2>{{ __('Purchase orders') }}</h2></div>
                @if ($orders->isEmpty())
                    <x-empty icon="file" :message="__('No purchase orders with this supplier.')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('PO number') }}</th><th>{{ __('Purchase request') }}</th><th>{{ __('Order date') }}</th><th class="text-right">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                        @foreach ($orders as $po)
                            <tr>
                                <td class="nowrap"><a href="{{ route('purchase-orders.show', $po) }}">{{ $po->po_number }}</a></td>
                                <td>{{ $po->request?->title ?? '—' }}</td>
                                <td class="nowrap">{{ fmt_date($po->order_date) }}</td>
                                <td class="num text-right nowrap">{{ money($po->total_amount, $po->currency) }}</td>
                                <td><x-status domain="purchase_order" :value="$po->status" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>

            <div class="card" id="quotations">
                <div class="card__head"><h2>{{ __('Quotations') }}</h2></div>
                @if ($quotations->isEmpty())
                    <x-empty icon="file" :message="__('No quotations recorded.')" />
                @else
                    <div class="table-wrap"><table class="table">
                        <thead><tr><th>{{ __('Purchase request') }}</th><th>{{ __('Received') }}</th><th class="text-right">{{ __('Amount') }}</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($quotations as $quotation)
                            <tr>
                                <td>@if ($quotation->request)<a href="{{ route('procurement.show', $quotation->request) }}">{{ $quotation->request->reference_number }}</a><span class="sub">{{ $quotation->request->title }}</span>@else — @endif</td>
                                <td class="nowrap">{{ fmt_date($quotation->received_on) }}</td>
                                <td class="num text-right nowrap">{{ money($quotation->amount, $quotation->currency) }}</td>
                                <td>@if ($quotation->is_selected)<span class="badge badge--success">{{ __('Selected') }}</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>

            <x-attachments :model="$supplier" :can-upload="$canManage" :title="__('Registration and contract documents')" />
            <x-audit-trail :model="$supplier" />
        </div>

        <div class="stack">
            <div class="card" id="performance">
                <div class="card__head"><h2>{{ __('Performance') }}</h2>@include('suppliers.partials.rating', ['value' => $average])</div>
                <div class="card__body">
                    @if ($supplier->evaluations->isEmpty())
                        <p class="muted">{{ __('No performance evaluations recorded yet.') }}</p>
                    @else
                        <dl class="dl">
                            @foreach ($criteria as $label => $value)
                                <dt>{{ $label }}</dt><dd>{{ number_format((float) $value, 1) }} / 5</dd>
                            @endforeach
                            <dt>{{ __('Evaluations') }}</dt><dd>{{ $supplier->evaluations->count() }}</dd>
                        </dl>
                    @endif
                </div>
                @if ($supplier->evaluations->isNotEmpty())
                    <ul class="list">
                        @foreach ($supplier->evaluations as $evaluation)
                            <li>
                                <div class="list__row">
                                    <div>
                                        <strong>{{ number_format($evaluation->score(), 1) }} / 5</strong>
                                        <span class="small muted">{{ __('Quality') }} {{ $evaluation->quality_rating }} · {{ __('Delivery') }} {{ $evaluation->delivery_rating }} · {{ __('Communication') }} {{ $evaluation->communication_rating }}</span>
                                        <div class="list__meta">{{ $evaluation->evaluator?->name ?? '—' }} · {{ fmt_date($evaluation->created_at) }}
                                            @if ($evaluation->purchaseOrder) · <a href="{{ route('purchase-orders.show', $evaluation->purchaseOrder) }}">{{ $evaluation->purchaseOrder->po_number }}</a>@endif</div>
                                        @if ($evaluation->comments)<div class="small prose">{{ $evaluation->comments }}</div>@endif
                                    </div>
                                    @if ($canManage)
                                        <x-delete-button :action="route('suppliers.evaluations.destroy', [$supplier, $evaluation])" :label="__('Remove')" class="btn-link danger" :confirm="__('Remove this evaluation?')" />
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($canManage)
                    <div class="card__foot">
                        @include('suppliers.partials.evaluation-form', ['supplier' => $supplier, 'orderOptions' => $orderOptions, 'selectedOrder' => null])
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
