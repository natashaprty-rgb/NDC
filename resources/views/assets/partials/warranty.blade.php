@php $reminderDays = (int) setting('warranty_reminder_days', 30); @endphp
@if ($asset->warranty_expiry === null)
    <span class="muted">—</span>
@elseif ($asset->isUnderWarranty())
    {{ fmt_date($asset->warranty_expiry) }}
    @if ($asset->warrantyExpiresWithin($reminderDays))
        <span class="badge badge--warning">{{ __('Expiring soon') }}</span>
    @else
        <span class="badge badge--success">{{ __('Under warranty') }}</span>
    @endif
@else
    {{ fmt_date($asset->warranty_expiry) }} <span class="badge badge--neutral">{{ __('Expired') }}</span>
@endif
