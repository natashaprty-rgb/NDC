@props(['title' => null, 'flush' => false])
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || isset($headerActions))
        <div class="card__header">
            @if($title)<h2>{{ $title }}</h2>@endif
            @isset($headerActions)<div class="flex">{{ $headerActions }}</div>@endisset
        </div>
    @endif
    <div class="card__body {{ $flush ? 'card__body--flush' : '' }}">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="card__footer">{{ $footer }}</div>
    @endisset
</section>
