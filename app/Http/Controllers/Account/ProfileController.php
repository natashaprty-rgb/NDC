<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Settings;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('roles', 'staff.department');
        $pendingSecret = $request->session()->get('mfa_setup_secret');

        return view('account.profile', [
            'user' => $user,
            'pendingSecret' => $pendingSecret,
            'provisioningUri' => $pendingSecret ? Totp::provisioningUri($pendingSecret, $user->email, (string) Settings::get('org.short_name', 'NDC')) : null,
            'recentLogins' => $user->loginActivities()->limit(10)->get(),
        ]);
    }

    public function updatePreferences(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(config('app.supported_locales'))]]);
        $request->user()->update($data);
        $request->session()->put('locale', $data['locale']);

        return back()->with('success', __('common.saved'));
    }

    public function editPassword(): View
    {
        return view('account.change-password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $request->input('password'),
            'password_changed_at' => now(),
            'must_change_password' => false,
        ])->save();

        // Sign out other sessions that used the old password.
        Auth::logoutOtherDevices($request->input('password'));
        $request->session()->regenerate();
        AuditLogger::log('password_changed', $user, 'User changed own password');

        return redirect()->route('dashboard')->with('success', __('auth.password_changed'));
    }

    public function startMfa(Request $request): RedirectResponse
    {
        abort_if($request->user()->hasMfaEnabled(), 409);
        $request->session()->put('mfa_setup_secret', Totp::generateSecret());

        return redirect()->route('profile.show')->withFragment('mfa');
    }

    public function enableMfa(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $secret = $request->session()->get('mfa_setup_secret');

        if (! $secret || ! Totp::verify($secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => __('auth.mfa_invalid')])->withFragment('mfa');
        }

        $codes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();
        $user = $request->user();
        $user->forceFill(['mfa_secret' => $secret, 'mfa_recovery_codes' => $codes, 'mfa_enabled_at' => now()])->save();
        $request->session()->forget('mfa_setup_secret');
        AuditLogger::log('mfa_enabled', $user, 'Multi-factor authentication enabled');

        return redirect()->route('profile.show')->with('recovery_codes', $codes)->with('success', __('auth.mfa_enabled'));
    }

    public function disableMfa(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);
        $user = $request->user();
        $user->forceFill(['mfa_secret' => null, 'mfa_recovery_codes' => null, 'mfa_enabled_at' => null])->save();
        AuditLogger::log('mfa_disabled', $user, 'Multi-factor authentication disabled');

        return redirect()->route('profile.show')->with('success', __('auth.mfa_disabled'));
    }
}
