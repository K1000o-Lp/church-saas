<?php

namespace App\Support\Events;

enum EventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public static function label(string $status): string
    {
        return match ($status) {
            self::Draft->value => 'Borrador',
            self::Published->value => 'Publicado',
            self::Cancelled->value => 'Cancelado',
            self::Completed->value => 'Completado',
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
        return in_array($status, [self::Draft->value, self::Published->value], true);
    }
}
