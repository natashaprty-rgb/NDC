@php $title = $order->exists ? __('Edit purchase order') : __('New purchase order'); @endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :subtitle="$pr->reference_number.' — '.$pr->title"
        :breadcrumbs="[__('Purchase requests') => route('procurement.index'), $pr->reference_number => route('procurement.show', $pr)]" />
    <form method="POST" action="{{ $order->exists ? route('purchase-orders.update', $order) : route('purchase-orders.store', $pr) }}" enctype="multipart/form-data" class="card form">
        @csrf
        @if ($order->exists) @method('PUT') @endif
        <div class="card__body">
            @unless ($order->exists)
                <p class="small muted">{{ __('A purchase order number is assigned automatically. The supplier and amount default to the selected quotation.') }}</p>
            @endunless
            <div class="form-grid">
                <x-form.select name="supplier_id" :label="__('Supplier')" :options="$suppliers" :value="$order->supplier_id" required />
                <x-form.select name="quotation_id" :label="__('Quotation')" :options="$quotations" :value="$order->quotation_id" :placeholder="__('— None —')" />
                <x-form.input name="order_date" type="date" :label="__('Order date')" :value="$order->order_date" required />
                <x-form.input name="expected_delivery_date" type="date" :label="__('Expected delivery date')" :value="$order->expected_delivery_date" />
                <x-form.input name="total_amount" type="number" step="0.01" min="0" :label="__('Order amount')" :value="$order->total_amount" required />
                <x-form.input name="currency" :label="__('Currency')" :value="$order->currency" required maxlength="3" />
                <x-form.input name="contract_reference" :label="__('Contract reference')" :value="$order->contract_reference" maxlength="255" />
                <x-form.input name="contract_end_date" type="date" :label="__('Contract end date')" :value="$order->contract_end_date" />
                <x-form.textarea name="terms" :label="__('Terms and conditions')" :value="$order->terms" rows="4" class="span-2" />
                <x-form.input type="file" name="file" :label="__('Signed order or contract')" class="span-2" />
            </div>
            <p class="small muted">{{ __('The system records orders only. Payments are processed outside the system.') }}</p>
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ $order->exists ? __('Save') : __('Issue purchase order') }}</button>
            <a href="{{ $order->exists ? route('purchase-orders.show', $order) : route('procurement.show', $pr) }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
