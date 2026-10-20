@php $title = $supplier->exists ? __('Edit supplier') : __('Register supplier'); @endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :breadcrumbs="[__('Suppliers') => route('suppliers.index')]" />
    <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}" class="card form">
        @csrf
        @if ($supplier->exists) @method('PUT') @endif
        <div class="card__body">
            <div class="form-grid">
                <x-form.input name="name" :label="__('Name')" :value="$supplier->name" required maxlength="255" class="span-2" />
                <x-form.select name="type" :label="__('Type')" :options="\App\Support\Status::options('supplier_type')" :value="$supplier->type" required :placeholder="false" />
                <x-form.select name="category_id" :label="__('Category')" :options="$categories" :value="$supplier->category_id" />
                <x-form.input name="registration_number" :label="__('Business registration number')" :value="$supplier->registration_number" maxlength="255" />
                <x-form.input name="tax_number" :label="__('Tax identification number')" :value="$supplier->tax_number" maxlength="255" />
                <x-form.input name="contact_person" :label="__('Contact person')" :value="$supplier->contact_person" maxlength="255" />
                <x-form.input name="email" type="email" :label="__('Email address')" :value="$supplier->email" maxlength="255" />
                <x-form.input name="phone" :label="__('Phone')" :value="$supplier->phone" maxlength="50" />
                <x-form.input name="website" type="url" :label="__('Website')" :value="$supplier->website" maxlength="255" placeholder="https://" />
                <x-form.input name="address" :label="__('Address')" :value="$supplier->address" maxlength="255" class="span-2" />
                <x-form.select name="status" :label="__('Status')" :options="\App\Support\Status::options('supplier')" :value="$supplier->status" required :placeholder="false"
                    :help="__('Only active suppliers can be chosen for new quotations and orders.')" />
                <x-form.textarea name="notes" :label="__('Notes')" :value="$supplier->notes" rows="3" class="span-2" />
            </div>
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $supplier->exists ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
