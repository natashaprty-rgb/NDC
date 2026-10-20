<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Read access to the status vocabularies defined in config/statuses.php.
 */
class Status
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function all(string $domain): array
    {
        $values = config("statuses.{$domain}");

        if (! is_array($values)) {
            throw new InvalidArgumentException("Unknown status domain [{$domain}].");
        }

        return $values;
    }

    /**
     * Allowed keys, for validation rules such as Rule::in(Status::keys('task')).
     *
     * @return list<string>
     */
    public static function keys(string $domain): array
    {
        return array_keys(self::all($domain));
    }

    /**
     * Translated value => label pairs for <select> options.
     *
     * @return array<string, string>
     */
    public static function options(string $domain): array
    {
        return array_map(fn (array $def) => __($def[0]), self::all($domain));
    }

    public static function label(string $domain, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $def = config("statuses.{$domain}.{$value}");

        return $def ? __($def[0]) : ucfirst(str_replace('_', ' ', $value));
    }

    public static function tone(string $domain, ?string $value): string
    {
        return config("statuses.{$domain}.{$value}.1", 'neutral');
    }
}
