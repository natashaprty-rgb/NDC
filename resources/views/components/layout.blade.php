@props(['title' => '', 'subtitle' => null, 'breadcrumbs' => []])
@php
    $user = auth()->user();
    $orgShort = \App\Support\Settings::get('org.short_name', 'NDC');
    $orgName = \App\Support\Settings::get('org.name', 'National Data Center Somalia');
    $unread = $user ? $user->unreadNotifications()->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · ' : '' }}{{ $orgShort }} {{ __('common.app_short') }}</title>
    <link rel="stylesheet" href="{{ asset('static/css/app.css') }}?v=1">
    <link rel="icon" href="{{ asset('static/img/placeholder-mark.svg') }}" type="image/svg+xml">
    <script src="{{ asset('static/js/app.js') }}?v=1" defer></script>
</head>
<body>
<a class="skip-link" href="#main">{{ __('common.skip_to_content') }}</a>
@unless(app()->isProduction())
    <div class="env-banner" role="note">{{ __('common.environment_banner', ['env' => app()->environment()]) }}</div>
@endunless
<div class="app-shell">
    <aside class="sidebar" id="sidebar" aria-label="{{ __('nav.main_navigation') }}">
        <a href="{{ route('dashboard') }}" class="sidebar__brand" style="text-decoration:none">
            {{-- Neutral placeholder mark: replace with the approved NDC logo (see public/static/img/README.md) --}}
            <span class="brand-mark" aria-hidden="true">{{ \Illuminate\Support\Str::limit($orgShort, 4, '') }}</span>
            <span class="brand-text"><strong>{{ $orgShort }} {{ __('common.app_short') }}</strong><span>{{ __('common.app_tagline') }}</span></span>
        </a>
        <nav>
            @foreach(\App\Support\Navigation::for($user) as $group => $items)
                <div class="nav-group">
                    <div class="nav-group__title">{{ __($group) }}</div>
                    @foreach($items as $item)
                        @php $active = request()->routeIs(...(array) $item['active']); @endphp
                        <a href="{{ route($item['route']) }}" class="nav-link {{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" />
                            <span>{{ __($item['label']) }}</span>
                            @if($item['route'] === 'notifications.index' && $unread > 0)
                                <span class="nav-badge">{{ $unread }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <button type="button" class="icon-btn menu-toggle" data-nav-toggle aria-controls="sidebar" aria-expanded="false">
                <x-icon name="menu" /><span class="sr-only">{{ __('nav.toggle_menu') }}</span>
            </button>
            <span class="muted small topbar__org">{{ $orgName }}</span>
            <div class="topbar__spacer"></div>

            <form method="POST" action="{{ route('locale.switch') }}" class="lang-switch" aria-label="{{ __('common.language') }}">
                @csrf
                @foreach(config('app.supported_locales') as $loc)
                    <button type="submit" name="locale" value="{{ $loc }}" aria-pressed="{{ app()->getLocale() === $loc ? 'true' : 'false' }}" lang="{{ $loc }}">{{ strtoupper($loc) }}</button>
                @endforeach
            </form>

            @if(Route::has('notifications.index'))
                <a href="{{ route('notifications.index') }}" class="icon-btn" title="{{ __('nav.notifications') }}">
                    <x-icon name="bell" />
                    <span class="sr-only">{{ __('nav.notifications') }}</span>
                    @if($unread > 0)<span class="count">{{ $unread > 99 ? '99+' : $unread }}</span>@endif
                </a>
            @endif

            <details class="user-menu">
                <summary>
                    <span class="avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(preg_replace('/[^\pL]+/u', '', $user->name) ?: '?', 0, 1)) }}</span>
                    <span class="user-name small">{{ $user->name }}</span>
                </summary>
                <div class="user-menu__panel">
                    <div class="user-menu__meta">{{ $user->email }}<br>{{ $user->roles->pluck('name')->join(', ') ?: __('common.no_role') }}</div>
                    <a href="{{ route('profile.show') }}">{{ __('nav.my_profile') }}</a>
                    <a href="{{ route('password.change') }}">{{ __('nav.change_password') }}</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">{{ __('nav.logout') }}</button>
                    </form>
                </div>
            </details>
        </header>

        <main id="main" class="content" tabindex="-1">
            @if(count($breadcrumbs))
                <nav aria-label="{{ __('common.breadcrumb') }}">
                    <ol class="breadcrumbs">
                        <li><a href="{{ route('dashboard') }}">{{ __('nav.dashboard') }}</a></li>
                        @foreach($breadcrumbs as $label => $url)
                            @if(is_int($label))
                                <li aria-current="page">{{ $url }}</li>
                            @else
                                <li><a href="{{ $url }}">{{ $label }}</a></li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif

            <div class="page-header">
                <div>
                    <h1>{{ $title }}</h1>
                    @if($subtitle)<p class="page-header__sub">{{ $subtitle }}</p>@endif
                </div>
                @isset($actions)
                    <div class="page-actions">{{ $actions }}</div>
                @endisset
            </div>

            <x-flash />

            {{ $slot }}
        </main>
    </div>
</div>

<dialog class="confirm" id="confirm-dialog" aria-labelledby="confirm-title">
    <div class="confirm__body">
        <h2 id="confirm-title">{{ __('common.please_confirm') }}</h2>
        <p data-confirm-message></p>
    </div>
    <div class="confirm__actions">
        <button type="button" class="btn" data-confirm-cancel>{{ __('common.cancel') }}</button>
        <button type="button" class="btn btn--danger" data-confirm-ok>{{ __('common.confirm') }}</button>
    </div>
</dialog>
</body>
</html>
