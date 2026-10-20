<?php

namespace App\Support\Plans;

final class SubscriptionStatus
{
    public const ACTIVE = 'active';

    public const PAST_DUE = 'past_due';

    public const CANCELLED = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::ACTIVE, self::PAST_DUE, self::CANCELLED];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ACTIVE => 'Activa',
            self::PAST_DUE => 'Pago pendiente',
            self::CANCELLED => 'Cancelada',
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

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? $status;
    }
}
