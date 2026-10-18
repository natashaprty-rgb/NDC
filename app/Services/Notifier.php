<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Single entry point modules use to notify people. Inactive accounts and
 * the user performing the action are skipped. Delivery failures are logged
 * and never break the business operation that triggered them.
 */
class Notifier
{
    /**
     * @param  User|iterable<User|null>|null  $recipients
     */
    public static function send(User|iterable|null $recipients, string $kind, string $titleKey, array $params = [], ?string $url = null, bool $includeActor = false): int
    {
        $users = Collection::wrap($recipients instanceof User ? [$recipients] : ($recipients ?? []))
            ->filter(fn ($u) => $u instanceof User && $u->isActive() && ! $u->trashed())
            ->when(! $includeActor && Auth::id(), fn ($c) => $c->reject(fn (User $u) => $u->id === Auth::id()))
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return 0;
        }

        // Store a relative URL so links keep working if APP_URL changes.
        if ($url && str_starts_with($url, url('/'))) {
            $url = substr($url, strlen(url('/'))) ?: '/';
        }

        try {
            Notification::send($users, new SystemNotification($kind, $titleKey, $params, $url));
        } catch (\Throwable $e) {
            Log::error('Notification dispatch failed', ['kind' => $kind, 'error' => $e->getMessage()]);

            return 0;
        }

        return $users->count();
    }
}
