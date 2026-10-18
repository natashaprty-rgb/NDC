@props(['action'])
<form method="GET" action="{{ $action }}" class="filter-bar" role="search">
    {{ $slot }}
    <div class="flex">
        <button type="submit" class="btn btn--primary btn--sm"><x-icon name="search" width="14" height="14" /> {{ __('common.filter') }}</button>
        <a href="{{ $action }}" class="btn btn--sm">{{ __('common.reset') }}</a>
    </div>
</form>
