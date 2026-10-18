@props(['name', 'label', 'id' => null, 'value' => null, 'required' => false, 'help' => null, 'rows' => 4, 'class' => ''])
@php
    $key = trim(str_replace(['[', ']'], ['.', ''], $name), '.');
    $id = unique_field_id($id ?? 'f_'.str_replace('.', '_', $key));
@endphp
<div class="field {{ $class }}">
    <label for="{{ $id }}">{{ $label }} @if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if($required) required @endif
        {{ $attributes->merge(['class' => 'input'.($errors->has($key) ? ' is-invalid' : '')]) }}
        @if($errors->has($key)) aria-invalid="true" aria-describedby="{{ $id }}_err" @endif>{{ old($key, $value) }}</textarea>
    @if($help)<span class="help">{{ $help }}</span>@endif
    @error($key)<span class="error" id="{{ $id }}_err">{{ $message }}</span>@enderror
</div>
