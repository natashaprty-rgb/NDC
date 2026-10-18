<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** Locale priority: user preference > session choice > system default. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('app.supported_locales', ['en']);
        $locale = $request->user()?->locale ?: $request->session()->get('locale') ?: Settings::get('locale.default', config('app.locale'));

        App::setLocale(in_array($locale, $supported, true) ? $locale : config('app.fallback_locale', 'en'));

        return $next($request);
    }
}
