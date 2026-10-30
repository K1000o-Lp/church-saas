<?php

namespace App\Support\Billing;

use InvalidArgumentException;

/**
 * Monetary values are stored as exact decimals (numeric(14,2)). All arithmetic
 * is performed internally on integer minor units so no float ever touches money.
 */
final class Money
{
    public const CURRENCY = 'USD';

    public static function normalize(string|int $amount): string
    {
        $amount = trim((string) $amount);

        if (! preg_match('/^-?\d{1,9}(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('El monto debe tener como máximo dos decimales.');
        }

        $negative = str_starts_with($amount, '-');
        $unsigned = ltrim($amount, '-');
        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    public static function toMinor(string|int $amount): int
    {
        $normalized = self::normalize($amount);
        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '-');
        [$whole, $fraction] = explode('.', $unsigned, 2);
        $minor = ((int) $whole) * 100 + (int) $fraction;

        return $negative ? -$minor : $minor;
    }

    public static function fromMinor(int $minor): string
    {
        $negative = $minor < 0;
        $absolute = abs($minor);
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;

        return ($negative ? '-' : '').$whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }

    public static function format(string|int $amount): string
    {
        return self::normalize($amount);
    }

    public static function decimal(string|int $amount): string
    {
        return self::normalize($amount);
    }

    public static function isZero(string|int $amount): bool
    {
        return self::toMinor($amount) === 0;
    }

    public static function compare(string|int $left, string|int $right): int
    {
        return self::toMinor($left) <=> self::toMinor($right);
    }

    public static function add(string|int ...$amounts): string
    {
        return self::fromMinor(array_sum(array_map(self::toMinor(...), $amounts)));
    }

    public static function subtract(string|int $left, string|int $right): string
    {
        return self::fromMinor(self::toMinor($left) - self::toMinor($right));
    }

    public static function maxZero(string|int $amount): string
    {
        return self::compare($amount, 0) < 0 ? '0.00' : self::normalize($amount);
    }
}
