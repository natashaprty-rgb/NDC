@php $editing = $supplier->exists; @endphp
<x-layout :title="$editing ? __('suppliers.edit') : __('suppliers.new')"
          :breadcrumbs="[__('suppliers.title') => route('suppliers.index'), ($editing ? $supplier->name : __('suppliers.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="code" :label="__('suppliers.code')" :value="$supplier->code" :required="$editing" maxlength="30" :help="$editing ? null : __('suppliers.code_help')" />
                <x-field.input name="name" :label="__('suppliers.name')" :value="$supplier->name" :required="true" maxlength="200" />
                <x-field.input name="registration_number" :label="__('suppliers.registration_number')" :value="$supplier->registration_number" maxlength="80" />
                <x-field.input name="tax_number" :label="__('suppliers.tax_number')" :value="$supplier->tax_number" maxlength="80" />
                <x-field.input name="contact_person" :label="__('suppliers.contact_person')" :value="$supplier->contact_person" maxlength="150" />
                <x-field.input name="email" type="email" :label="__('suppliers.email')" :value="$supplier->email" />
                <x-field.input name="phone" :label="__('suppliers.phone')" :value="$supplier->phone" maxlength="40" />
                <x-field.input name="country" :label="__('suppliers.country')" :value="$supplier->country" maxlength="80" />
                <x-field.input name="address" :label="__('suppliers.address')" :value="$supplier->address" maxlength="500" class="span-2" />
                <x-field.input name="services" :label="__('suppliers.services')" :value="$supplier->services" maxlength="500" class="span-2" />
                <x-field.select name="status" :label="__('common.status')" :options="enum_options(\App\Models\Supplier::STATUSES)" :value="$supplier->status" :required="true" :placeholder="false" />
                <x-field.textarea name="notes" :label="__('common.notes')" :value="$supplier->notes" class="span-2" />
            </div>
            <div class="form-actions">
                <a href="{{ $editing ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
