<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes entries to the audit trail. Failures are logged but never break the
 * user's request.
 */
class Audit
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function log(
        string $event,
        ?Model $subject = null,
        ?string $description = null,
        ?array $old = null,
        ?array $new = null,
        ?int $userId = null,
    ): ?AuditLog {
        try {
            $request = app()->runningInConsole() ? null : request();

            return AuditLog::query()->create([
                'user_id' => $userId ?? Auth::id(),
                'event' => $event,
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'description' => $description !== null ? mb_substr($description, 0, 255) : null,
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 512) : null,
                'url' => $request ? mb_substr($request->fullUrl(), 0, 2048) : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Audit log write failed', ['event' => $event, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
