<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Exact money arithmetic using integer minor units (cents).
 *
 * Money is stored as DECIMAL(15,2) and read from the database as a string
 * (e.g. "1250.50"). Converting strings to integer cents avoids every
 * floating-point rounding problem. bcmath is not required.
 */
final class Money
{
    /** Convert a decimal value ("1,250.5", "1250.50", 12, "-3.1") to integer cents. */
    public static function toCents(string|int|null $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_int($value)) {
            return $value * 100;
        }

        $value = str_replace([',', ' '], '', trim($value));
        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,}))?$/', $value, $m)) {
            throw new InvalidArgumentException("Invalid money amount [{$value}].");
        }

        $fraction = $m[3] ?? '';
        if (strlen($fraction) > 2 && trim(substr($fraction, 2), '0') !== '') {
            throw new InvalidArgumentException("Money amount [{$value}] has more than two decimal places.");
        }

        $cents = ((int) $m[2]) * 100 + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return ($m[1] ?? '') === '-' ? -$cents : $cents;
    }

    /** Integer cents to a plain decimal string suitable for a DECIMAL column ("1250.50"). */
    public static function fromCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return $sign.intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Human readable amount with thousands separators: "1,250.50". */
    public static function format(string|int|null $amount, ?string $currency = null): string
    {
        $cents = is_int($amount) ? $amount : self::toCents($amount);
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);
        $text = $sign.number_format(intdiv($abs, 100)).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return $currency ? $currency.' '.$text : $text;
    }

    /** Sum decimal strings exactly; returns a decimal string. */
    public static function sum(iterable $amounts): string
    {
        $total = 0;
        foreach ($amounts as $amount) {
            $total += self::toCents($amount === null ? null : (string) $amount);
        }

        return self::fromCents($total);
    }

    /** quantity (up to 2 decimals) x unit price, rounded half-up to the cent. */
    public static function multiply(string|int $quantity, string|int $unitPrice): string
    {
        $qtyHundredths = self::toCents(is_int($quantity) ? $quantity : (string) $quantity); // quantity * 100
        $priceCents = self::toCents(is_int($unitPrice) ? $unitPrice : (string) $unitPrice);
        $product = $qtyHundredths * $priceCents; // cents * 100
        $cents = intdiv($product + ($product >= 0 ? 50 : -50), 100);

        return self::fromCents($cents);
    }

    /** Percentage (0-100, one decimal) of part over whole, safe for zero. */
    public static function percent(string|int $part, string|int $whole): float
    {
        $w = is_int($whole) ? $whole : self::toCents($whole);
        if ($w === 0) {
            return 0.0;
        }
        $p = is_int($part) ? $part : self::toCents($part);

        return round($p * 100 / $w, 1);
    }
}
