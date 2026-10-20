@props(['domain', 'value'])
<span {{ $attributes->merge(['class' => 'badge badge--'.\App\Support\Status::tone($domain, $value)]) }}>{{ \App\Support\Status::label($domain, $value) }}</span>
