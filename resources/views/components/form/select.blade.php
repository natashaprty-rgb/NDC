@props(['name', 'label' => null, 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null, 'multiple' => false, 'class' => ''])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $key = str_replace(['[', ']'], ['.', ''], rtrim($name, '[]'));
    $current = old($key, $value);
    $selected = collect(is_iterable($current) ? $current : [$current])->map(fn ($v) => (string) $v)->all();
@endphp
<div @class(['field', 'has-error' => $errors->has($key), $class])>
    @if ($label)
        <label for="{{ $id }}">{{ $label }}@if ($required) <span class="req" aria-hidden="true">*</span>@endif</label>
    @endif
    <select {{ $attributes->except('id')->merge(['class' => 'select']) }} id="{{ $id }}" name="{{ $name }}" @required($required) @if($multiple) multiple @endif
        @if ($errors->has($key)) aria-invalid="true" @endif>
        @if (! $multiple && $placeholder !== false)
            <option value="">{{ $placeholder ?? __('— Select —') }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected(in_array((string) $optValue, $selected, true))>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if ($help)
        <div class="help">{{ $help }}</div>
    @endif
    @error($key)
        <div class="field-error">{{ $message }}</div>
    @enderror
</div>
