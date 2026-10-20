@php
    $title = $asset->exists ? __('Edit asset') : __('Register asset');
    $specRows = old('specifications', collect($asset->specifications ?? [])->map(fn ($value, $key) => ['key' => $key, 'value' => $value])->values()->all());
    if ($specRows === []) {
        $specRows = [['key' => '', 'value' => '']];
    }
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" :breadcrumbs="array_filter([__('Assets') => route('assets.index'), ($asset->exists ? $asset->asset_tag : '') => $asset->exists ? route('assets.show', $asset) : null])" />
    <form method="POST" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}" class="card form">
        @csrf
        @if ($asset->exists) @method('PUT') @endif
        <div class="card__body">
            <fieldset>
                <legend>{{ __('Identification') }}</legend>
                <div class="form-grid">
                    <x-form.input name="name" :label="__('Asset name')" :value="$asset->name" required maxlength="255" :help="__('For example: Dell Latitude 5440 laptop, HP LaserJet printer, office desk.')" />
                    <x-form.input name="asset_tag" :label="__('Asset tag')" :value="$asset->asset_tag" maxlength="40"
                        :help="$asset->exists ? __('Unique identifier of this asset.') : __('Leave blank to generate the next tag automatically.')" />
                    <x-form.select name="category_id" :label="__('Category')" :options="$categories" :value="$asset->category_id"
                        :help="__('Categories are managed under Administration › Reference Data.')" />
                    <x-form.select name="condition" :label="__('Condition')" :options="\App\Support\Status::options('asset_condition')" :value="$asset->condition" required :placeholder="false" />
                    <x-form.input name="manufacturer" :label="__('Manufacturer')" :value="$asset->manufacturer" maxlength="255" />
                    <x-form.input name="model" :label="__('Model')" :value="$asset->model" maxlength="255" />
                    <x-form.input name="serial_number" :label="__('Serial number')" :value="$asset->serial_number" maxlength="255" />
                </div>
            </fieldset>
            <fieldset>
                <legend>{{ __('Purchase and warranty') }}</legend>
                <div class="form-grid">
                    <x-form.select name="supplier_id" :label="__('Supplier')" :options="$suppliers" :value="$asset->supplier_id" />
                    <x-form.input name="purchase_date" type="date" :label="__('Purchase date')" :value="$asset->purchase_date" />
                    <x-form.input name="purchase_cost" type="number" step="0.01" min="0" :label="__('Purchase cost')" :value="$asset->purchase_cost" />
                    <x-form.input name="currency" :label="__('Currency')" :value="$asset->currency" required maxlength="3" :help="__('Three-letter code, e.g. USD.')" />
                    <x-form.input name="warranty_expiry" type="date" :label="__('Warranty expiry')" :value="$asset->warranty_expiry" />
                </div>
            </fieldset>
            <fieldset>
                <legend>{{ __('Location and ownership') }}</legend>
                <div class="form-grid">
                    <x-form.select name="department_id" :label="__('Department')" :options="$departments" :value="$asset->department_id" />
                    <x-form.input name="location" :label="__('Location')" :value="$asset->location" maxlength="255" :help="__('Building, floor, room or rack.')" />
                    <x-form.textarea name="notes" :label="__('Notes')" :value="$asset->notes" rows="3" class="span-2" />
                </div>
                @unless ($asset->exists)
                    <p class="small muted">{{ __('Use “Assign” on the asset page to issue it to an employee or department after registration.') }}</p>
                @endunless
            </fieldset>
            <fieldset data-repeater data-repeater-next="{{ (int) collect(array_keys($specRows))->max() + 1 }}">
                <legend>{{ __('Technical specifications') }}</legend>
                <p class="small muted">{{ __('Optional details such as processor, memory, IP address, rack position or power draw.') }}</p>
                <div data-repeater-rows>
                    @foreach ($specRows as $i => $row)
                        <div class="repeater-row" data-repeater-row style="grid-template-columns:minmax(0,1fr) minmax(0,2fr) auto">
                            <x-form.input name="specifications[{{ $i }}][key]" :label="__('Attribute')" :value="$row['key'] ?? ''" maxlength="100" />
                            <x-form.input name="specifications[{{ $i }}][value]" :label="__('Value')" :value="$row['value'] ?? ''" maxlength="500" />
                            <button type="button" class="btn btn-sm btn-ghost" data-repeater-remove aria-label="{{ __('Remove row') }}"><x-icon name="x" /></button>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template>
                    <div class="repeater-row" data-repeater-row style="grid-template-columns:minmax(0,1fr) minmax(0,2fr) auto">
                        <div class="field"><label for="f_spec___INDEX___key">{{ __('Attribute') }}</label><input class="input" id="f_spec___INDEX___key" name="specifications[__INDEX__][key]" maxlength="100"></div>
                        <div class="field"><label for="f_spec___INDEX___value">{{ __('Value') }}</label><input class="input" id="f_spec___INDEX___value" name="specifications[__INDEX__][value]" maxlength="500"></div>
                        <button type="button" class="btn btn-sm btn-ghost" data-repeater-remove aria-label="{{ __('Remove row') }}"><x-icon name="x" /></button>
                    </div>
                </template>
                <button type="button" class="btn btn-sm" data-repeater-add style="margin-top:.6rem"><x-icon name="plus" /> {{ __('Add specification') }}</button>
            </fieldset>
        </div>
        <div class="card__foot form-actions">
            <button class="btn btn-primary">{{ __('Save') }}</button>
            <a href="{{ $asset->exists ? route('assets.show', $asset) : route('assets.index') }}" class="btn btn-ghost">{{ __('Cancel') }}</a>
        </div>
    </form>
</x-layouts.app>
