<?php

namespace App\Support\Segments;

enum SegmentType: string
{
    case Dynamic = 'dynamic';
    case Static = 'static';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Dynamic => 'Dinámico',
            self::Static => 'Estático',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Dynamic => 'Se actualiza solo con las reglas que definas.',
            self::Static => 'Tú eliges y mantienes las personas que lo integran.',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
            'description' => $type->description(),
        ], self::cases());
    }
}
