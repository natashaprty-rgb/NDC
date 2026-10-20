@props(['value' => 0, 'tone' => null])
@php
    $value = max(0, min(100, (int) $value));
    $tone ??= $value >= 100 ? null : null;
@endphp
<div class="progress-row" {{ $attributes }}>
    <div class="progress" role="progressbar" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="100">
        <div @class(['progress__bar', 'progress__bar--warn' => $tone === 'warn', 'progress__bar--danger' => $tone === 'danger']) style="width: {{ $value }}%"></div>
    </div>
    <span>{{ $value }}%</span>
</div>
