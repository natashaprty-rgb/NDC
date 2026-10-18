<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 300;

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = $this->throttleKey($request);
        $this->ensureNotLockedOut($request, $key);

        $user = User::query()->where('email', strtolower($credentials['email']))->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            event(new Failed('web', $user, ['email' => $credentials['email']]));

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->isActive()) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            event(new Failed('web', $user, ['email' => $credentials['email']]));

            throw ValidationException::withMessages(['email' => __('auth.suspended')]);
        }

        RateLimiter::clear($key);

        if ($user->hasMfaEnabled()) {
            $request->session()->put('mfa', [
                'user_id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            return redirect()->route('mfa.challenge');
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    public function showMfaChallenge(Request $request): View|RedirectResponse
    {
        if (! $this->pendingMfaUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.mfa-challenge');
    }

    public function verifyMfa(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:32']]);

        $user = $this->pendingMfaUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['email' => __('auth.mfa_expired')]);
        }

        $key = 'mfa|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['code' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key), 'minutes' => ceil(RateLimiter::availableIn($key) / 60)])]);
        }

        $code = trim((string) $request->input('code'));
        $valid = Totp::verify((string) $user->mfa_secret, $code) || $this->consumeRecoveryCode($user, $code);

        if (! $valid) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            \App\Models\LoginActivity::query()->create([
                'user_id' => $user->id, 'email' => $user->email, 'event' => 'mfa_failed',
                'ip_address' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
            ]);

            throw ValidationException::withMessages(['code' => __('auth.mfa_invalid')]);
        }

        RateLimiter::clear($key);
        $remember = (bool) $request->session()->get('mfa.remember', false);
        $request->session()->forget('mfa');

        return $this->completeLogin($request, $user, $remember);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('auth.logged_out'));
    }

    private function completeLogin(Request $request, User $user, bool $remember): RedirectResponse
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();
        if ($user->locale) {
            $request->session()->put('locale', $user->locale);
        }

        return redirect()->intended(route('dashboard'));
    }

    private function pendingMfaUser(Request $request): ?User
    {
        $pending = $request->session()->get('mfa');
        if (! is_array($pending) || ($pending['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget('mfa');

            return null;
        }
        $user = User::query()->find($pending['user_id'] ?? 0);

        return $user && $user->isActive() && $user->hasMfaEnabled() ? $user : null;
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->mfa_recovery_codes ?? [];
        $normalised = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
        foreach ($codes as $i => $stored) {
            if ($normalised !== '' && hash_equals(strtoupper(str_replace('-', '', $stored)), $normalised)) {
                unset($codes[$i]);
                $user->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    private function ensureNotLockedOut(Request $request, string $key): void
    {
        if (! RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($request));
        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages(['email' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)])]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());
    }
}
