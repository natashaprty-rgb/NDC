@props(['value' => 0])
@php $v = max(0, min(100, (int) $value)); @endphp
<div class="flex" style="flex-wrap:nowrap">
    <div class="progress" role="progressbar" aria-valuenow="{{ $v }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('common.progress') }}" style="flex:1">
        <div class="progress__bar {{ $v >= 100 ? 'progress__bar--success' : '' }}" style="width: {{ $v }}%"></div>
    </div>
    <span class="small muted nowrap">{{ $v }}%</span>
</div>
