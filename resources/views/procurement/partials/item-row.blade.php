{{-- One line item of a purchase request; also rendered with index "__INDEX__" as the repeater template. --}}
@php
    $field = fn (string $name) => "items.{$index}.{$name}";
    $val = fn (string $name, $default = '') => $index === '__INDEX__' ? $default : old($field($name), $item[$name] ?? $default);
@endphp
<div class="repeater-row" data-repeater-row style="grid-template-columns:minmax(0,3fr) minmax(0,1fr) minmax(0,1fr) minmax(0,1.3fr) auto">
    <div @class(['field', 'has-error' => $errors->has($field('description'))])>
        <label for="item_{{ $index }}_description">{{ __('Item description') }} <span class="req" aria-hidden="true">*</span></label>
        <input class="input" id="item_{{ $index }}_description" name="items[{{ $index }}][description]" value="{{ $val('description') }}" maxlength="255" required>
        @if ($errors->has($field('description')))<div class="field-error">{{ $errors->first($field('description')) }}</div>@endif
    </div>
    <div @class(['field', 'has-error' => $errors->has($field('quantity'))])>
        <label for="item_{{ $index }}_quantity">{{ __('Quantity') }} <span class="req" aria-hidden="true">*</span></label>
        <input class="input" type="number" step="0.01" min="0.01" id="item_{{ $index }}_quantity" name="items[{{ $index }}][quantity]" value="{{ $val('quantity', 1) }}" required>
        @if ($errors->has($field('quantity')))<div class="field-error">{{ $errors->first($field('quantity')) }}</div>@endif
    </div>
    <div @class(['field', 'has-error' => $errors->has($field('unit'))])>
        <label for="item_{{ $index }}_unit">{{ __('Unit') }}</label>
        <input class="input" id="item_{{ $index }}_unit" name="items[{{ $index }}][unit]" value="{{ $val('unit', 'unit') }}" maxlength="30">
    </div>
    <div @class(['field', 'has-error' => $errors->has($field('estimated_unit_cost'))])>
        <label for="item_{{ $index }}_cost">{{ __('Estimated unit cost') }} <span class="req" aria-hidden="true">*</span></label>
        <input class="input" type="number" step="0.01" min="0" id="item_{{ $index }}_cost" name="items[{{ $index }}][estimated_unit_cost]" value="{{ $val('estimated_unit_cost') }}" required>
        @if ($errors->has($field('estimated_unit_cost')))<div class="field-error">{{ $errors->first($field('estimated_unit_cost')) }}</div>@endif
    </div>
    <button type="button" class="btn btn-sm btn-ghost" data-repeater-remove aria-label="{{ __('Remove item') }}"><x-icon name="trash" /></button>
    <div class="field" style="grid-column:1 / -1">
        <label for="item_{{ $index }}_specs">{{ __('Specifications') }}</label>
        <input class="input" id="item_{{ $index }}_specs" name="items[{{ $index }}][specifications]" value="{{ $val('specifications') }}" maxlength="2000">
    </div>
</div>
