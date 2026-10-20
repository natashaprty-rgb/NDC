@if ($paginator->hasPages() || $paginator->total() > 0)
    <nav class="pagination" aria-label="{{ __('Pagination') }}">
        <div>
            @if ($paginator->total())
                {{ __('Showing :from–:to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
            @endif
        </div>
        @if ($paginator->hasPages())
            <ul>
                <li>
                    @if ($paginator->onFirstPage())
                        <span class="page is-disabled" aria-disabled="true">‹</span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('Previous') }}">‹</a>
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
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('Next') }}">›</a>
                    @else
                        <span class="page is-disabled" aria-disabled="true">›</span>
                    @endif
                </li>
            </ul>
        @endif
    </nav>
@endif
