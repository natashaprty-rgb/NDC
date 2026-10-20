<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Support\Audit;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many sign-in attempts. Please try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($throttleKey),
                ]),
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);
            event(new Failed('web', $user, $credentials));

            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        if (! $user->isActive()) {
            Audit::log('login_blocked', $user, __('Inactive account'), userId: $user->id);

            throw ValidationException::withMessages(['email' => __('Your account is not active. Contact the system administrator.')]);
        }

        RateLimiter::clear($throttleKey);

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('login.2fa', [
                'id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires' => now()->addMinutes(5)->timestamp,
            ]);

            return redirect()->route('two-factor.challenge');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function challenge(Request $request): View|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function verifyChallenge(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $throttleKey = '2fa|'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages(['code' => __('Too many attempts. Please sign in again later.')]);
        }

        if (! $twoFactor->attempt($user, $request->string('code')->toString())) {
            RateLimiter::hit($throttleKey, 300);
            Audit::log('two_factor_failed', $user, null, userId: $user->id);

            throw ValidationException::withMessages(['code' => __('The verification code is invalid.')]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) $request->session()->pull('login.2fa.remember');
        $request->session()->forget('login.2fa');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('login.2fa');

        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget('login.2fa');

            return null;
        }

        $user = User::query()->find($pending['id']);

        return $user && $user->isActive() && $user->hasTwoFactorEnabled() ? $user : null;
    }
}
