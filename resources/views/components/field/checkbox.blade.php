@props(['name', 'label', 'checked' => false, 'value' => '1', 'class' => ''])
@php
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = 'f_'.str_replace('.', '_', $key).'_'.md5((string) $value);
    $isChecked = old() ? (bool) old($key) : $checked;
@endphp
<div class="field {{ $class }}">
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="checkbox" for="{{ $id }}">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="{{ $value }}" @checked($isChecked) {{ $attributes }}>
        <span>{{ $label }}</span>
    </label>
    @error($key)<span class="error">{{ $message }}</span>@enderror
</div>
