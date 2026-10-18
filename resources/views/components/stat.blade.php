@props(['label', 'value', 'href' => null, 'meta' => null, 'tone' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'stat'.($tone ? ' stat--'.$tone : '')]) }}>
    <div class="stat__label">{{ $label }}</div>
    <div class="stat__value">{{ $value }}</div>
    @if($meta)<div class="stat__meta">{{ $meta }}</div>@endif
</{{ $tag }}>
