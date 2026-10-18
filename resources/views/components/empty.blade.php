@props(['message' => null, 'icon' => 'inbox'])
<div class="empty">
    <x-icon :name="$icon" class="icon" width="34" height="34" />
    <p class="mb-0">{{ $message ?? __('common.no_records') }}</p>
    @if(trim($slot))<div class="mt-1">{{ $slot }}</div>@endif
</div>
