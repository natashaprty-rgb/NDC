<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Runtime settings stored in the "settings" table, falling back to the
 * defaults in config('ndc.settings'). Values are cached until changed.
 */
class Settings
{
    private const CACHE_KEY = 'ndc.settings';

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $values = $this->all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $defaults = config('ndc.settings', []);

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->pluck('value', 'key')
                    ->map(fn ($value) => json_decode((string) $value, true))
                    ->all();
            });
        } catch (Throwable) {
            // The database may be unavailable during installation.
            $stored = [];
        }

        return $this->loaded = array_merge($defaults, $stored);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function set(array $values, ?int $userId = null): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => json_encode($value), 'updated_by' => $userId],
            );
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    /**
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return array_values(array_filter(array_map(
            fn ($ext) => strtolower(trim($ext)),
            explode(',', (string) $this->get('upload_allowed_extensions', '')),
        )));
    }
}
