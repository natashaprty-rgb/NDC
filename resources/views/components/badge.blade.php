@props(['value', 'label' => null])
@php
    $tones = [
        'success' => ['completed', 'approved', 'active', 'done', 'delivered', 'received', 'attended', 'paid', 'reconciled', 'in_use', 'responded', 'closed_ok', 'successful', 'passed', 'sent', 'good', 'new'],
        'warning' => ['on_hold', 'pending', 'pending_review', 'awaiting_approval', 'submitted', 'mitigating', 'partially_received', 'partially_delivered', 'under_maintenance', 'postponed', 'on_leave', 'in_progress_warn', 'fair', 'recorded', 'medium', 'scheduled_warn', 'excused'],
        'danger' => ['cancelled', 'rejected', 'overdue', 'suspended', 'blacklisted', 'lost', 'expired', 'terminated', 'missed', 'failed', 'critical', 'high', 'urgent', 'damaged', 'poor', 'absent', 'restricted', 'open_risk'],
        'info' => ['in_progress', 'assigned', 'issued', 'ordered', 'scheduled', 'registered', 'confidential', 'invited'],
        'primary' => ['planned', 'draft', 'todo', 'open', 'in_stock', 'internal'],
    ];
    $tone = collect($tones)->search(fn ($values) => in_array($value, $values, true)) ?: 'neutral';
@endphp
<span {{ $attributes->merge(['class' => 'badge badge--'.$tone]) }}>{{ $label ?? status_label($value) }}</span>
