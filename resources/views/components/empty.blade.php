@props(['message' => null, 'icon' => 'inbox'])
<div class="empty">
    <x-icon :name="$icon" />
    <div>{{ $message ?? __('No records found.') }}</div>
    {{ $slot }}
</div>
