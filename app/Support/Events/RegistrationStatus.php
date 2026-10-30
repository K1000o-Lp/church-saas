<?php

namespace App\Support\Events;

enum RegistrationStatus: string
{
    case Registered = 'registered';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';

    public static function label(string $status): string
    {
        return match ($status) {
            self::Registered->value => 'Registrado',
            self::Confirmed->value => 'Confirmado',
            self::Declined->value => 'Rechazado',
            self::Waitlisted->value => 'En espera',
            self::Cancelled->value => 'Cancelado',
            default => $status,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status): array => [
            'value' => $status->value,
            'label' => self::label($status->value),
        ], self::cases());
    }

    public static function occupiesSeat(string $status): bool
    {
        return in_array($status, [self::Registered->value, self::Confirmed->value], true);
    }
}
