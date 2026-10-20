@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'class' => ''])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $current = $type === 'password' ? null : old($key, $value instanceof \DateTimeInterface ? $value->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d') : $value);
@endphp
<div @class(['field', 'has-error' => $errors->has($key), $class])>
    @if ($label)
        <label for="{{ $id }}">{{ $label }}@if ($required) <span class="req" aria-hidden="true">*</span>@endif</label>
    @endif
    <input {{ $attributes->except('id')->merge(['class' => 'input']) }} type="{{ $type }}" id="{{ $id }}" name="{{ $name }}"
        @if ($type !== 'file') value="{{ $current }}" @endif @required($required)
        @if ($errors->has($key)) aria-invalid="true" aria-describedby="{{ $id }}_error" @endif>
    @if ($help)
        <div class="help">{{ $help }}</div>
    @endif
    @error($key)
        <div class="field-error" id="{{ $id }}_error">{{ $message }}</div>
    @enderror
</div>
