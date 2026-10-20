<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chooses the interface language: the signed-in user's preference, then the
 * session choice (guests), then the system default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('ndc.locales'));
        $locale = $request->user()?->locale
            ?? $request->session()->get('locale')
            ?? setting('default_locale', config('app.locale'));

        app()->setLocale(in_array($locale, $supported, true) ? $locale : config('app.fallback_locale'));

        return $next($request);
    }
}
