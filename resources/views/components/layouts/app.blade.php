@props(['title' => null])
@php
    $locale = app()->getLocale();
    $dir = config("ndc.locales.$locale.dir", 'ltr');
    $user = auth()->user();
    $unread = $user?->unreadNotifications()->count() ?? 0;
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('NDC Management System') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('css/ndc.css') }}?v={{ filemtime(public_path('css/ndc.css')) }}">
</head>
<body>
<a href="#main" class="skip-link">{{ __('Skip to content') }}</a>
<div class="app" data-app>
    <aside class="sidebar" id="sidebar" aria-label="{{ __('Main navigation') }}">
        <a href="{{ route('dashboard') }}" class="sidebar__brand">
            <img src="{{ asset('images/logo-white.png') }}" alt="{{ __('National Data Center Somalia') }}" width="210" height="35">
        </a>
        <nav class="nav">
            @foreach (config('ndc.navigation') as $group)
                @php
                    $items = collect($group['items'])->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item['route'])
                        && ($item['can'] === null || $user->can($item['can'])));
                @endphp
                @if ($items->isNotEmpty())
                    @if ($group['section'])
                        <div class="nav__section">{{ __($group['section']) }}</div>
                    @endif
                    @foreach ($items as $item)
                        @php $active = request()->routeIs(...(array) $item['active']); @endphp
                        <a href="{{ route($item['route']) }}" @class(['is-active' => $active]) @if($active) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" />
                            <span>{{ __($item['label']) }}</span>
                        </a>
                    @endforeach
                @endif
            @endforeach
        </nav>
    </aside>
    <div class="sidebar-backdrop" data-nav-close></div>

    <div class="main">
        <header class="topbar">
            <button type="button" class="topbar__menu" data-nav-toggle aria-controls="sidebar" aria-label="{{ __('Open menu') }}">
                <x-icon name="menu" />
            </button>
            <div class="topbar__title">{{ $title }}</div>
            <div class="topbar__actions">
                <form method="POST" action="{{ route('locale.switch') }}" class="lang-switch hide-sm" aria-label="{{ __('Language') }}">
                    @csrf
                    @foreach (config('ndc.locales') as $code => $meta)
                        <button type="submit" name="locale" value="{{ $code }}" @class(['is-active' => $code === $locale]) lang="{{ $code }}" aria-pressed="{{ $code === $locale ? 'true' : 'false' }}">{{ strtoupper($code) }}</button>
                    @endforeach
                </form>
                <a href="{{ route('notifications.index') }}" class="icon-btn" aria-label="{{ __('Notifications') }}{{ $unread ? ' ('.$unread.')' : '' }}">
                    <x-icon name="bell" />
                    @if ($unread)<span class="dot">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
                </a>
                <div class="dropdown" data-dropdown>
                    <button type="button" class="dropdown__toggle" data-dropdown-toggle aria-haspopup="true" aria-expanded="false">
                        <span class="avatar">{{ $user->initials() }}</span>
                        <span class="hide-sm">{{ $user->name }}</span>
                    </button>
                    <div class="dropdown__menu" role="menu">
                        <div class="small muted" style="padding:.4rem .7rem">{{ $user->email }}<br>{{ $user->getRoleNames()->map(fn ($r) => __($r))->join(', ') }}</div>
                        <a href="{{ route('profile.edit') }}" role="menuitem">{{ __('My profile') }}</a>
                        <a href="{{ route('profile.security') }}" role="menuitem">{{ __('Security & sessions') }}</a>
                        <form method="POST" action="{{ route('locale.switch') }}">
                            @csrf
                            @foreach (config('ndc.locales') as $code => $meta)
                                @if ($code !== $locale)
                                    <button type="submit" name="locale" value="{{ $code }}" role="menuitem" lang="{{ $code }}">{{ $meta['native'] }}</button>
                                @endif
                            @endforeach
                        </form>
                        <hr style="margin:.3rem 0">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" role="menuitem">{{ __('Sign out') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="content" tabindex="-1">
            <x-flash />
            {{ $slot }}
        </main>

        <footer class="footer">
            &copy; {{ now()->year }} {{ setting('organisation_name', __('National Data Center Somalia')) }} · {{ __('NDC Management System') }}
        </footer>
    </div>
</div>
<script src="{{ asset('js/ndc.js') }}?v={{ filemtime(public_path('js/ndc.js')) }}"></script>
</body>
</html>
