<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Forces users flagged with must_change_password to set a new password first. */
class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('password.change', 'password.change.update', 'logout', 'locale.switch')) {
            return redirect()->route('password.change')->with('warning', __('auth.must_change_password'));
        }

        return $next($request);
    }
}
