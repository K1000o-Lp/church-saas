<?php

namespace App\Support\Services;

enum ServiceStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public static function label(string $status): string
    {
        return match ($status) {
            self::Draft->value => 'Borrador',
            self::Published->value => 'Publicado',
            self::Completed->value => 'Completado',
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
}
