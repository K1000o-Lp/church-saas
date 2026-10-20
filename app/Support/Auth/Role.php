<?php

namespace App\Support\Auth;

enum Role: string
{
    case Admin = 'admin';
    case Pastor = 'pastor';
    case Finance = 'finance';
    case Leader = 'leader';
    case Volunteer = 'volunteer';
    case Member = 'member';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role): string => $role->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Pastor => 'Pastor',
            self::Finance => 'Finanzas',
            self::Leader => 'Líder',
            self::Volunteer => 'Voluntario',
            self::Member => 'Miembro',
        };
    }
}
