<?php

use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('fmt_date')) {
    /**
     * Format a date using the configured date format; "—" when empty.
     */
    function fmt_date(CarbonInterface|string|null $date, bool $withTime = false): string
    {
        if ($date === null || $date === '') {
            return '—';
        }

        $date = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $format = $withTime ? setting('datetime_format', 'd M Y H:i') : setting('date_format', 'd M Y');

        return $date->copy()->locale(app()->getLocale())->translatedFormat($format);
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount with thousands separators and the currency code.
     */
    function money(float|int|string|null $amount, ?string $currency = null): string
    {
        $currency ??= setting('currency', 'USD');

        return $currency.' '.number_format((float) $amount, 2);
    }
}

if (! function_exists('format_bytes')) {
    function format_bytes(int|float|null $bytes): string
    {
        $bytes = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (int) $bytes : number_format($bytes, 1)).' '.$units[$i];
    }
}
