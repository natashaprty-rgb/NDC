@props(['column', 'label'])
@php
    $current = request('sort');
    $dir = request('dir', 'desc');
    $isSorted = $current === $column;
    $nextDir = $isSorted && $dir === 'asc' ? 'desc' : 'asc';
@endphp
<a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $nextDir, 'page' => null]) }}" @class(['is-sorted' => $isSorted]) @if($isSorted) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $label }}@if ($isSorted) {{ $dir === 'asc' ? '↑' : '↓' }}@endif
</a>
