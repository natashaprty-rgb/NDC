<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /** Attributes that are never written to the audit trail. */
    public const REDACTED = ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes'];

    public static function log(string $event, ?Model $subject = null, ?string $description = null, array $old = [], array $new = []): AuditLog
    {
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::query()->create([
            'user_id' => Auth::id(),
            'event' => $event,
            'auditable_type' => $subject ? $subject->getMorphClass() : null,
            'auditable_id' => $subject?->getKey(),
            'description' => $description ? Str::limit($description, 250) : null,
            'old_values' => self::clean($old) ?: null,
            'new_values' => self::clean($new) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 490) : null,
            'url' => $request ? Str::limit($request->fullUrl(), 490) : null,
        ]);
    }

    private static function clean(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::REDACTED, true)) {
                $values[$key] = '[redacted]';
            } elseif (is_string($value) && mb_strlen($value) > 2000) {
                $values[$key] = mb_substr($value, 0, 2000).'…';
            }
        }

        return $values;
    }
}
