@props(['name' => 'file', 'label' => null, 'required' => false, 'class' => ''])
@php $id = unique_field_id('f_'.$name); @endphp
<div class="field {{ $class }}">
    <label for="{{ $id }}">{{ $label ?? __('common.file') }} @if($required)<span class="req" aria-hidden="true">*</span>@endif</label>
    <input id="{{ $id }}" type="file" name="{{ $name }}" class="input" @if($required) required @endif
        accept="{{ collect(\App\Services\FileStorage::allowedExtensions())->map(fn ($e) => '.'.$e)->join(',') }}" {{ $attributes }}>
    <span class="help">{{ __('common.upload_help', ['types' => implode(', ', \App\Services\FileStorage::allowedExtensions()), 'size' => round(\App\Services\FileStorage::maxKb() / 1024, 1)]) }}</span>
    @error($name)<span class="error">{{ $message }}</span>@enderror
</div>
