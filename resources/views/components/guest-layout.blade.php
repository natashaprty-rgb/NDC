@props(['title' => ''])
@php $orgShort = \App\Support\Settings::get('org.short_name', 'NDC'); @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}{{ $orgShort }} {{ __('common.app_short') }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}?v=1">
    <link rel="icon" href="{{ asset('assets/img/placeholder-mark.svg') }}" type="image/svg+xml">
    <script src="{{ asset('assets/js/app.js') }}?v=1" defer></script>
</head>
<body>
<main class="auth-page">
    <div class="auth-card">
        <div class="brand">
            <span class="brand-mark" aria-hidden="true">{{ \Illuminate\Support\Str::limit($orgShort, 4, '') }}</span>
            <div>
                <strong>{{ \App\Support\Settings::get('org.name', 'National Data Center Somalia') }}</strong>
                <div class="muted small">{{ __('common.app_name') }}</div>
            </div>
        </div>
        <h1 style="font-size:1.2rem">{{ $title }}</h1>
        <x-flash />
        {{ $slot }}
        <form method="POST" action="{{ route('locale.switch') }}" class="lang-switch mt-2" style="width:max-content" aria-label="{{ __('common.language') }}">
            @csrf
            @foreach(config('app.supported_locales') as $loc)
                <button type="submit" name="locale" value="{{ $loc }}" aria-pressed="{{ app()->getLocale() === $loc ? 'true' : 'false' }}" lang="{{ $loc }}">{{ $loc === 'so' ? 'Soomaali' : 'English' }}</button>
            @endforeach
        </form>
    </div>
</main>
</body>
</html>
