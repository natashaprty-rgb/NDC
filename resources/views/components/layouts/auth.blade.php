@props(['title' => null])
@php
    $locale = app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ config("ndc.locales.$locale.dir", 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('NDC Management System') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/ndc.css') }}?v={{ filemtime(public_path('css/ndc.css')) }}">
</head>
<body>
<div class="auth">
    <section class="auth__brand" aria-hidden="true">
        <img src="{{ asset('images/logo-white.png') }}" alt="">
        <p>{{ __('Management Information System for project coordination, records, resources and reporting.') }}</p>
    </section>
    <main class="auth__panel">
        <div class="auth__box">
            <img class="auth__logo-sm" src="{{ asset('images/logo-horizontal.png') }}" alt="{{ __('National Data Center Somalia') }}">
            <form method="POST" action="{{ route('locale.switch') }}" class="lang-switch" aria-label="{{ __('Language') }}">
                @csrf
                @foreach (config('ndc.locales') as $code => $meta)
                    <button type="submit" name="locale" value="{{ $code }}" @class(['is-active' => $code === $locale]) lang="{{ $code }}">{{ $meta['native'] }}</button>
                @endforeach
            </form>
            <x-flash />
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
