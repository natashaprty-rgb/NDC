<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out suspended or deactivated accounts immediately, and sends users
 * who must change their password (or must enrol in two-factor
 * authentication) to the security page before anything else.
 */
class EnsureAccountIsUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if (! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('Your account is not active. Contact the system administrator.')]);
        }

        if ($request->routeIs('profile.*', 'logout', 'locale.switch')) {
            return $next($request);
        }

        if ($user->must_change_password) {
            return redirect()->route('profile.security')->with('warning', __('You must change your password before continuing.'));
        }

        if (setting('require_two_factor_for_admins')
            && ! $user->hasTwoFactorEnabled()
            && $user->hasAnyPermission(['users.manage', 'roles.manage', 'settings.manage', 'system.manage'])) {
            return redirect()->route('profile.security')->with('warning', __('Administrators must enable two-factor authentication.'));
        }

        return $next($request);
    }
}
