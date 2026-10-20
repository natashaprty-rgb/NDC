@props(['name', 'label', 'checked' => false, 'value' => '1', 'class' => ''])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name).'_'.$value);
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $isChecked = old() ? (bool) old($key) : (bool) $checked;
@endphp
<div @class(['field', $class])>
    <label class="check" for="{{ $id }}">
        <input type="hidden" name="{{ $name }}" value="0">
        <input {{ $attributes->except('id') }} type="checkbox" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" @checked($isChecked)>
        {{ $label }}
    </label>
    @error($key)
        <div class="field-error">{{ $message }}</div>
    @enderror
</div>
