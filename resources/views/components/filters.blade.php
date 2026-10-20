@props(['action' => null])
{{-- GET filter bar; keeps the current sort order when filtering. --}}
<form method="GET" action="{{ $action ?? url()->current() }}" class="filters" role="search">
    {{ $slot }}
    @foreach (['sort', 'dir'] as $keep)
        @if (request($keep))
            <input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">
        @endif
    @endforeach
    <div class="field" style="flex:0 0 auto;min-width:0">
        <button type="submit" class="btn btn-primary">{{ __('Filter') }}</button>
        @if (collect(request()->except(['page', 'sort', 'dir']))->filter()->isNotEmpty())
            <a href="{{ $action ?? url()->current() }}" class="btn btn-ghost">{{ __('Reset') }}</a>
        @endif
    </div>
</form>
