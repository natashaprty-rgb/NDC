@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => null, 'help' => null, 'class' => ''])
@php
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = 'f_'.str_replace('.', '_', $key);
    $current = (string) old($key, $value);
@endphp
<div class="field {{ $class }}">
    <label for="{{ $id }}">{{ $label }} @if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @if($required) required @endif
        {{ $attributes->merge(['class' => 'input'.($errors->has($key) ? ' is-invalid' : '')]) }}
        @if($errors->has($key)) aria-invalid="true" aria-describedby="{{ $id }}_err" @endif>
        @if($placeholder !== false)
            <option value="">{{ $placeholder ?? __('common.select_option') }}</option>
        @endif
        @foreach($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected($current !== '' && $current === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @if($help)<span class="help">{{ $help }}</span>@endif
    @error($key)<span class="error" id="{{ $id }}_err">{{ $message }}</span>@enderror
</div>
