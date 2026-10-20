{{-- Expiry and review badges for a document. --}}
@if ($document->isExpired())
    <span class="badge badge--danger">{{ __('Expired') }}</span>
@elseif ($document->isExpiringWithin($expiryDays))
    <span class="badge badge--warning">{{ __('Expires in :days days', ['days' => (int) today()->diffInDays($document->expiry_date)]) }}</span>
@endif
@if ($document->isReviewDue() && ! $document->isArchived())
    <span class="badge badge--info">{{ __('Review due') }}</span>
@endif
