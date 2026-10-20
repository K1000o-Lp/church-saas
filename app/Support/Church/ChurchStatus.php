<?php

namespace App\Support\Church;

final class ChurchStatus
{
    public const ACTIVE = 'active';

    public const SUSPENDED = 'suspended';

    public const INACTIVE = 'inactive';

    public const ARCHIVED = 'archived';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [self::ACTIVE, self::SUSPENDED, self::INACTIVE, self::ARCHIVED];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ACTIVE => 'Activa',
            self::SUSPENDED => 'Suspendida',
            self::INACTIVE => 'Inactiva',
            self::ARCHIVED => 'Archivada',
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

    public static function blocksAccess(string $status): bool
    {
        return $status !== self::ACTIVE;
    }

    public static function canEdit(string $status): bool
    {
        return $status !== self::ARCHIVED;
    }
}
