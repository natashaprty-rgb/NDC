@php $editing = $contract->exists; @endphp
<x-layout :title="$editing ? __('suppliers.edit_contract') : __('suppliers.new_contract')"
          :breadcrumbs="[__('suppliers.title') => route('suppliers.index'), $supplier->name => route('suppliers.show', $supplier), ($editing ? $contract->reference : __('suppliers.new_contract'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('contracts.update', $contract) : route('contracts.store', $supplier) }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="title" :label="__('suppliers.contract_title')" :value="$contract->title" :required="true" maxlength="200" class="span-2" />
                <x-field.input name="start_date" type="date" :label="__('suppliers.start_date')" :value="$contract->start_date" />
                <x-field.input name="end_date" type="date" :label="__('suppliers.end_date')" :value="$contract->end_date" />
                <x-field.input name="contract_value" :label="__('suppliers.contract_value')" :value="$contract->contract_value" inputmode="decimal" />
                <x-field.input name="currency" :label="__('suppliers.currency')" :value="$contract->currency" :required="true" maxlength="3" />
                <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\SupplierContract::STATUSES)" :value="$contract->status" :required="true" :placeholder="false" />
                <x-field.textarea name="scope" :label="__('suppliers.scope')" :value="$contract->scope" class="span-2" />
                <x-field.textarea name="notes" :label="__('common.notes')" :value="$contract->notes" class="span-2" />
            </div>
            <div class="form-actions">
                <a href="{{ $editing ? route('contracts.show', $contract) : route('suppliers.show', $supplier) }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
