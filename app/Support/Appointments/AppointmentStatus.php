<?php

namespace App\Support\Appointments;

enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Rescheduled = 'rescheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public static function label(string $status): string
    {
        return match ($status) {
            self::Scheduled->value => 'Programada',
            self::Rescheduled->value => 'Reagendada',
            self::Completed->value => 'Completada',
            self::Cancelled->value => 'Cancelada',
            self::NoShow->value => 'No asistió',
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

    public static function isOpen(string $status): bool
    {
        return in_array($status, [self::Scheduled->value, self::Rescheduled->value], true);
    }
}
