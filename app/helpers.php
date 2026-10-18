<?php

use App\Support\Money;
use App\Support\Settings;
use Illuminate\Support\Carbon;

if (! function_exists('money')) {
    /** Format a DECIMAL string / cents for display, e.g. "USD 1,250.00". */
    function money(string|int|null $amount, ?string $currency = null): string
    {
        return Money::format($amount ?? '0', $currency ?? (string) Settings::get('format.currency', 'USD'));
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(mixed $date, string $fallback = '—'): string
    {
        if (! $date) {
            return $fallback;
        }

        return Carbon::parse($date)->format((string) Settings::get('format.date', 'd/m/Y'));
    }
}

if (! function_exists('fmt_datetime')) {
    function fmt_datetime(mixed $date, string $fallback = '—'): string
    {
        if (! $date) {
            return $fallback;
        }

        return Carbon::parse($date)->format((string) Settings::get('format.datetime', 'd/m/Y H:i'));
    }
}

if (! function_exists('status_label')) {
    /** Translated label for any status/enum value (lang/{locale}/status.php). */
    function status_label(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $key = 'status.'.$value;
        $translated = __($key);

        return $translated === $key ? ucfirst(str_replace('_', ' ', $value)) : $translated;
    }
}

if (! function_exists('enum_options')) {
    /** ['value' => 'Translated label'] for a list of enum values. */
    function enum_options(array $values): array
    {
        return collect($values)->mapWithKeys(fn ($v) => [$v => status_label($v)])->all();
    }
}

if (! function_exists('route_if')) {
    /** URL for a named route, or null when the route is not registered. */
    function route_if(string $name, mixed $parameters = []): ?string
    {
        return \Illuminate\Support\Facades\Route::has($name) ? route($name, $parameters) : null;
    }
}

if (! function_exists('unique_field_id')) {
    /** Returns $id, suffixed if it was already used in this request (pages with several inline forms). */
    function unique_field_id(string $id): string
    {
        $used = request()->attributes->get('_field_ids', []);
        $candidate = $id;
        $n = 1;
        while (isset($used[$candidate])) {
            $candidate = $id.'_'.(++$n);
        }
        $used[$candidate] = true;
        request()->attributes->set('_field_ids', $used);

        return $candidate;
    }
}
