<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    /** Always returns the same message so the form cannot reveal which emails exist. */
    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $user = User::query()->where('email', strtolower((string) $request->input('email')))->first();
        if ($user && $user->isActive()) {
            Password::broker()->sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', __('auth.reset_link_sent'));
    }

    public function edit(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'password_changed_at' => now(),
                    'must_change_password' => false,
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __('auth.password_reset_done'))
            : back()->withInput($request->only('email'))->withErrors(['email' => __('auth.reset_invalid')]);
    }
}
