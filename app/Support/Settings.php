<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime application settings with defaults from config/ndc.php.
 */
final class Settings
{
    private const CACHE_KEY = 'ndc.settings';

    private static ?array $loaded = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? config('ndc.settings_defaults.'.$key);
    }

    public static function all(): array
    {
        if (self::$loaded !== null) {
            return self::$loaded;
        }

        $stored = [];
        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                return Setting::query()->get()->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])->all();
            });
        } catch (\Throwable) {
            // Database not available yet (fresh install): fall back to defaults.
        }

        return self::$loaded = array_merge(config('ndc.settings_defaults', []), $stored);
    }

    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        $type = match (true) {
            is_bool($value) => 'bool',
            is_int($value) => 'int',
            is_array($value) => 'json',
            default => 'string',
        };

        Setting::query()->updateOrCreate(['key' => $key], [
            'value' => $type === 'json' ? json_encode($value) : ($type === 'bool' ? ($value ? '1' : '0') : (string) $value),
            'type' => $type,
            'updated_by' => $userId,
        ]);

        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        self::$loaded = null;
    }
}
