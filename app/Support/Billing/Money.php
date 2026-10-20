<?php

namespace App\Support\Billing;

use InvalidArgumentException;

/**
 * Billing is USD-only in this phase. Amounts are always integers in cents;
 * decimal input is parsed with strings so no float ever touches money.
 */
final class Money
{
    public const CURRENCY = 'USD';

    public static function toMinor(string $amount): int
    {
        $amount = trim($amount);

        if (! preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException('El monto debe tener como máximo dos decimales.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');

        return ((int) $whole) * 100 + (int) $fraction;
    }

    public static function decimal(int $minor): string
    {
        $whole = intdiv($minor, 100);
        $fraction = $minor % 100;

        return $whole.'.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
    }
}
