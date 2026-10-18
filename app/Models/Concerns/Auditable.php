<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Writes created / updated / deleted / restored events to audit_logs.
 * Models may define `protected array $auditExclude = [...]` for noisy columns.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            AuditLogger::log('created', $model, null, [], $model->auditableAttributes($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->auditableAttributes($model->getChanges());
            if ($changes === []) {
                return;
            }
            $old = array_intersect_key($model->getOriginal(), $changes);
            AuditLogger::log('updated', $model, null, $model->auditableAttributes($old), $changes);
        });

        static::deleted(function (Model $model) {
            $soft = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();
            AuditLogger::log($soft ? 'archived' : 'deleted', $model);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => AuditLogger::log('restored', $model));
        }
    }

    public function auditableAttributes(array $attributes): array
    {
        $exclude = array_merge(['created_at', 'updated_at', 'updated_by'], $this->auditExclude ?? []);

        return array_diff_key($attributes, array_flip($exclude));
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }
}
