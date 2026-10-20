<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The signed-in user's own profile, password, two-factor authentication and
 * active sessions.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', ['user' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'locale' => ['required', Rule::in(array_keys(config('ndc.locales')))],
        ]);

        $user->update($data);

        return back()->with('success', __('Profile updated.'));
    }

    public function security(Request $request, TwoFactorService $twoFactor): View
    {
        $user = $request->user();
        $setupSecret = $request->session()->get('two_factor.setup_secret');

        return view('profile.security', [
            'user' => $user,
            'setupSecret' => $setupSecret,
            'qrCode' => $setupSecret ? $twoFactor->qrCodeSvg($user, $setupSecret) : null,
            'recoveryCodes' => $request->session()->get('two_factor.recovery_codes'),
            'sessions' => $this->sessions($request),
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults(), 'different:current_password'],
        ]);

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        Auth::logoutOtherDevices($request->input('password'));
        Audit::log('password_changed', $user, __('Password changed'));

        return back()->with('success', __('Password changed. Other sessions have been signed out.'));
    }

    public function startTwoFactor(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->session()->put('two_factor.setup_secret', $twoFactor->generateSecret());

        return redirect()->route('profile.security');
    }

    public function confirmTwoFactor(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $secret = $request->session()->get('two_factor.setup_secret');

        if (! $secret || ! $twoFactor->verify($secret, $request->string('code')->toString())) {
            return back()->withErrors(['code' => __('The verification code is invalid.')]);
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget('two_factor.setup_secret');
        $request->session()->flash('two_factor.recovery_codes', $codes);
        Audit::log('two_factor_enabled', $request->user());

        return redirect()->route('profile.security')->with('success', __('Two-factor authentication is now enabled.'));
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        Audit::log('two_factor_disabled', $request->user());

        return back()->with('success', __('Two-factor authentication has been disabled.'));
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        Auth::logoutOtherDevices($request->input('current_password'));

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $request->user()->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }
        Audit::log('sessions_revoked', $request->user());

        return back()->with('success', __('All other sessions have been signed out.'));
    }

    /**
     * @return list<object>
     */
    private function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($s) => (object) [
                'ip_address' => $s->ip_address,
                'user_agent' => mb_substr((string) $s->user_agent, 0, 120),
                'last_active' => Carbon::createFromTimestamp($s->last_activity),
                'is_current' => $s->id === $request->session()->getId(),
            ])->all();
    }
}
