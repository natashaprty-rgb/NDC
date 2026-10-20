@props(['name', 'label' => null, 'value' => null, 'required' => false, 'help' => null, 'rows' => 4, 'class' => ''])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $key = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div @class(['field', 'has-error' => $errors->has($key), $class])>
    @if ($label)
        <label for="{{ $id }}">{{ $label }}@if ($required) <span class="req" aria-hidden="true">*</span>@endif</label>
    @endif
    <textarea {{ $attributes->except('id')->merge(['class' => 'textarea']) }} id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
        @if ($errors->has($key)) aria-invalid="true" @endif>{{ old($key, $value) }}</textarea>
    @if ($help)
        <div class="help">{{ $help }}</div>
    @endif
    @error($key)
        <div class="field-error">{{ $message }}</div>
    @enderror
</div>
