@if ($paginator->hasPages() || $paginator->total() > 0)
<nav class="pagination" aria-label="{{ __('common.pagination') }}">
    <span>{{ __('common.showing_results', ['from' => $paginator->firstItem() ?? 0, 'to' => $paginator->lastItem() ?? 0, 'total' => $paginator->total()]) }}</span>
    @if ($paginator->hasPages())
    <ul>
        <li>
            @if ($paginator->onFirstPage())
                <span class="page is-disabled" aria-disabled="true">&lsaquo;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('common.previous') }}">&lsaquo;</a>
            @endif
        </li>
        @foreach ($elements as $element)
            @if (is_string($element))
                <li><span class="page is-disabled">{{ $element }}</span></li>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    <li>
                        @if ($page == $paginator->currentPage())
                            <span class="page is-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}">{{ $page }}</a>
                        @endif
                    </li>
                @endforeach
            @endif
        @endforeach
        <li>
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('common.next') }}">&rsaquo;</a>
            @else
                <span class="page is-disabled" aria-disabled="true">&rsaquo;</span>
            @endif
        </li>
    </ul>
    @endif
</nav>
@endif
