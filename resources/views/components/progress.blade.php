@props(['value' => 0, 'tone' => null, 'label' => null])
@php
    $value = max(0, min(100, (int) $value));
@endphp
<div class="progress-row" {{ $attributes }}>
    <div class="progress" role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $label ?? __('Progress') }}">
        <div @class(['progress__bar', 'progress__bar--warn' => $tone === 'warn', 'progress__bar--danger' => $tone === 'danger']) style="width: {{ $value }}%"></div>
    </div>
    <span>{{ $value }}%</span>
</div>
