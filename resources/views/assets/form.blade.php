@php
    $editing = $asset->exists;
    $specs = old('specifications', $asset->specifications ?? []);
    if ($specs === []) { $specs = [['key' => '', 'value' => '']]; }
@endphp
<x-layout :title="$editing ? __('assets.edit') : __('assets.new')"
          :breadcrumbs="[__('assets.title') => route('assets.index'), ($editing ? $asset->asset_tag : __('assets.new'))]">
    <x-card>
        <form method="POST" action="{{ $editing ? route('assets.update', $asset) : route('assets.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif
            <div class="form-grid">
                <x-field.input name="asset_tag" :label="__('assets.asset_tag')" :value="$asset->asset_tag" maxlength="40" :help="$editing ? null : __('assets.tag_help')" />
                <x-field.input name="name" :label="__('assets.name')" :value="$asset->name" :required="true" maxlength="200" />
                <x-field.select name="category_id" :label="__('assets.category')" :options="$categories" :value="$asset->category_id" :required="true" />
                <x-field.select name="condition" :label="__('assets.condition')" :options="enum_options(\App\Models\Asset::CONDITIONS)" :value="$asset->condition" :required="true" :placeholder="false" />
                <x-field.input name="manufacturer" :label="__('assets.manufacturer')" :value="$asset->manufacturer" maxlength="120" />
                <x-field.input name="model" :label="__('assets.model')" :value="$asset->model" maxlength="120" />
                <x-field.input name="serial_number" :label="__('assets.serial_number')" :value="$asset->serial_number" maxlength="120" />
                <x-field.input name="purchase_date" type="date" :label="__('assets.purchase_date')" :value="$asset->purchase_date" />
                <x-field.input name="purchase_cost" type="number" step="0.01" min="0" :label="__('assets.purchase_cost')" :value="$asset->purchase_cost" />
                <x-field.input name="currency" :label="__('assets.currency')" :value="$asset->currency" :required="true" maxlength="3" />
                <x-field.select name="supplier_id" :label="__('assets.supplier')" :options="$suppliers" :value="$asset->supplier_id" :placeholder="__('common.none')" />
                <x-field.select name="purchase_order_id" :label="__('assets.purchase_order')" :options="$purchaseOrders" :value="$asset->purchase_order_id" :placeholder="__('common.none')" />
                <x-field.input name="warranty_expiry" type="date" :label="__('assets.warranty_expiry')" :value="$asset->warranty_expiry" />
                <x-field.select name="location_id" :label="__('assets.location')" :options="$locations" :value="$asset->location_id" :placeholder="__('common.not_set')" />
                <x-field.select name="department_id" :label="__('common.department')" :options="$departments" :value="$asset->department_id" :placeholder="__('common.not_set')" />
                @unless($editing)
                    <x-field.select name="assigned_staff_id" :label="__('assets.initial_assignee')" :options="$staffList" :placeholder="__('assets.keep_in_stock')" />
                @endunless
                <x-field.textarea name="description" :label="__('common.description')" :value="$asset->description" class="span-2" rows="3" />
                <x-field.textarea name="notes" :label="__('common.notes')" :value="$asset->notes" class="span-2" rows="2" />
            </div>

            <fieldset class="mt-2" data-repeater>
                <legend>{{ __('assets.specifications') }}</legend>
                <p class="help small muted">{{ __('assets.specifications_help') }}</p>
                <div data-repeater-list class="stack">
                    @foreach($specs as $i => $row)
                        <div class="form-grid" data-repeater-row style="grid-template-columns: 2fr 3fr auto; align-items:end">
                            <x-field.input name="specifications[{{ $i }}][key]" :label="__('assets.spec_key')" :value="$row['key'] ?? ''" maxlength="100" />
                            <x-field.input name="specifications[{{ $i }}][value]" :label="__('assets.spec_value')" :value="$row['value'] ?? ''" maxlength="500" />
                            <button type="button" class="btn btn--sm" data-repeater-remove>{{ __('common.remove') }}</button>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template>
                    <div class="form-grid" data-repeater-row style="grid-template-columns: 2fr 3fr auto; align-items:end">
                        <div class="field"><label for="spec___INDEX___key">{{ __('assets.spec_key') }}</label><input id="spec___INDEX___key" class="input" name="specifications[__INDEX__][key]" maxlength="100"></div>
                        <div class="field"><label for="spec___INDEX___value">{{ __('assets.spec_value') }}</label><input id="spec___INDEX___value" class="input" name="specifications[__INDEX__][value]" maxlength="500"></div>
                        <button type="button" class="btn btn--sm" data-repeater-remove>{{ __('common.remove') }}</button>
                    </div>
                </template>
                <button type="button" class="btn btn--sm mt-1" data-repeater-add><x-icon name="plus" width="14" height="14" /> {{ __('assets.add_spec') }}</button>
            </fieldset>

            <div class="form-actions">
                <a href="{{ $editing ? route('assets.show', $asset) : route('assets.index') }}" class="btn">{{ __('common.cancel') }}</a>
                <button type="submit" class="btn btn--primary">{{ $editing ? __('common.save_changes') : __('common.create') }}</button>
            </div>
        </form>
    </x-card>
</x-layout>
