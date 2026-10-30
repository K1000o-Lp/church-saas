<?php

namespace App\Support\People;

enum RelationshipType: string
{
    case Spouse = 'spouse';
    case Parent = 'parent';
    case Child = 'child';
    case Sibling = 'sibling';
    case Guardian = 'guardian';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => 'Cónyuge',
            self::Parent => 'Padre/Madre',
            self::Child => 'Hijo/a',
            self::Sibling => 'Hermano/a',
            self::Guardian => 'Tutor/a',
            self::Other => 'Otro',
        };
    }

    public function inverse(): ?self
    {
        return match ($this) {
            self::Spouse => self::Spouse,
            self::Sibling => self::Sibling,
            self::Parent => self::Child,
            self::Child => self::Parent,
            self::Guardian, self::Other => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $type): string => $type->value, self::cases());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type): array => [
            'value' => $type->value,
            'label' => $type->label(),
        ], self::cases());
    }
}
