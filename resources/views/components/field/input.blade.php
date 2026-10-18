@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'help' => null, 'class' => ''])
@php
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = 'f_'.str_replace('.', '_', $key);
    $current = $type === 'password' ? null : old($key, $value instanceof \DateTimeInterface ? $value->format($type === 'datetime-local' ? 'Y-m-d\TH:i' : 'Y-m-d') : $value);
@endphp
<div class="field {{ $class }}">
    <label for="{{ $id }}">{{ $label }} @if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}" @if($required) required @endif
        {{ $attributes->merge(['class' => 'input'.($errors->has($key) ? ' is-invalid' : '')]) }}
        @if($errors->has($key)) aria-invalid="true" aria-describedby="{{ $id }}_err" @elseif($help) aria-describedby="{{ $id }}_help" @endif>
    @if($help)<span class="help" id="{{ $id }}_help">{{ $help }}</span>@endif
    @error($key)<span class="error" id="{{ $id }}_err">{{ $message }}</span>@enderror
</div>
