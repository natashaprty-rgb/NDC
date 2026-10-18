<?php

namespace App\Listeners;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

/** Records login, logout, failed login, lockout and password reset events. */
class RecordAuthenticationActivity
{
    public function handle(Login|Logout|Failed|Lockout|PasswordReset $event): void
    {
        $request = request();

        [$type, $user, $email] = match (true) {
            $event instanceof Login => ['login', $event->user, $event->user->email ?? null],
            $event instanceof Logout => ['logout', $event->user, $event->user?->email],
            $event instanceof Failed => ['failed', $event->user, $event->credentials['email'] ?? null],
            $event instanceof Lockout => ['lockout', null, $event->request->input('email')],
            $event instanceof PasswordReset => ['password_reset', $event->user, $event->user->email ?? null],
        };

        LoginActivity::query()->create([
            'user_id' => $user instanceof User ? $user->id : null,
            'email' => $email ? Str::limit(strtolower((string) $email), 250, '') : null,
            'event' => $type,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);

        if ($event instanceof Login && $user instanceof User) {
            $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        }
    }
}
