@if ($value === null)
    <span class="muted">{{ __('Not rated') }}</span>
@else
    <span @class(['badge', 'badge--success' => $value >= 4, 'badge--warning' => $value >= 2.5 && $value < 4, 'badge--danger' => $value < 2.5])>{{ number_format($value, 1) }} / 5</span>
@endif
