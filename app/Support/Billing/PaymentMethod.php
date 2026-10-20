<?php

namespace App\Support\Billing;

final class PaymentMethod
{
    public const CASH = 'cash';

    public const TRANSFER = 'transfer';

    public const CARD = 'card';

    public const MOBILE_PAYMENT = 'mobile_payment';

    public const OTHER = 'other';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::CASH, self::TRANSFER, self::CARD, self::MOBILE_PAYMENT, self::OTHER];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::CASH => 'Efectivo',
            self::TRANSFER => 'Transferencia',
            self::CARD => 'Tarjeta',
            self::MOBILE_PAYMENT => 'Pago móvil',
            self::OTHER => 'Otro',
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::labels())
            ->map(fn (string $label, string $value): array => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function label(string $method): string
    {
        return self::labels()[$method] ?? $method;
    }
}
