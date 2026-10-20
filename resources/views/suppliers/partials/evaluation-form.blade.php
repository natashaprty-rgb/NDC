@php
    $scale = collect(range(5, 1))->mapWithKeys(fn ($n) => [$n => $n.' — '.match ($n) {
        5 => __('Excellent'), 4 => __('Good'), 3 => __('Satisfactory'), 2 => __('Poor'), default => __('Unacceptable'),
    }])->all();
@endphp
<form method="POST" action="{{ route('suppliers.evaluations.store', $supplier) }}" class="stack" style="margin-top:.75rem">
    @csrf
    <h3>{{ __('Record performance evaluation') }}</h3>
    <x-form.select name="purchase_order_id" id="eval_po" :label="__('Purchase order')" :options="$orderOptions" :value="$selectedOrder ?? null" :placeholder="__('— Not linked to an order —')" />
    <x-form.select name="quality_rating" id="eval_quality" :label="__('Quality')" :options="$scale" required />
    <x-form.select name="delivery_rating" id="eval_delivery" :label="__('Delivery')" :options="$scale" required />
    <x-form.select name="communication_rating" id="eval_communication" :label="__('Communication')" :options="$scale" required />
    <x-form.textarea name="comments" id="eval_comments" :label="__('Comments')" rows="2" />
    <div><button class="btn btn-primary">{{ __('Save evaluation') }}</button></div>
</form>
