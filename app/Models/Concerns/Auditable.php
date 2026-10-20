<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Records create/update/delete/restore events for the model in the audit log.
 *
 * Hidden attributes and anything listed in $auditExclude are never written;
 * attributes listed in $auditMask are recorded as "[changed]" only.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            Audit::log('created', $model, $model->auditLabel(), null, $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (Model $model) {
            $changes = $model->auditableValues($model->getChanges());
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->auditableValues($model->getOriginal()), $changes);
            Audit::log('updated', $model, $model->auditLabel(), $old, $changes);
        });

        static::deleted(function (Model $model) {
            $soft = method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();
            Audit::log($soft ? 'archived' : 'deleted', $model, $model->auditLabel());
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                Audit::log('restored', $model, $model->auditLabel());
            });
        }
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('created_at');
    }

    /**
     * Human readable label stored with each entry.
     */
    public function auditLabel(): string
    {
        foreach (['reference_number', 'code', 'asset_tag', 'po_number', 'title', 'name', 'full_name'] as $field) {
            if (! empty($this->attributes[$field])) {
                return (string) $this->attributes[$field];
            }
        }

        return class_basename($this).' #'.$this->getKey();
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        $exclude = array_merge(
            ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'created_at', 'updated_at'],
            $this->getHidden(),
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );
        $mask = property_exists($this, 'auditMask') ? $this->auditMask : [];

        $result = [];
        foreach ($values as $key => $value) {
            if (in_array($key, $exclude, true)) {
                continue;
            }
            if (in_array($key, $mask, true)) {
                $result[$key] = '[changed]';

                continue;
            }
            $result[$key] = is_string($value) && mb_strlen($value) > 500 ? mb_substr($value, 0, 500).'…' : $value;
        }

        return $result;
    }
}
