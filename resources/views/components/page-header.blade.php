@props(['title', 'subtitle' => null, 'breadcrumbs' => []])
<div class="page-head">
    <div>
        @if ($breadcrumbs)
            <nav class="breadcrumbs" aria-label="{{ __('Breadcrumb') }}">
                @foreach ($breadcrumbs as $label => $url)
                    <a href="{{ $url }}">{{ $label }}</a> /
                @endforeach
            </nav>
        @endif
        <h1>{{ $title }}</h1>
        @if ($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @isset($actions)
        <div class="page-head__actions">{{ $actions }}</div>
    @endisset
</div>
