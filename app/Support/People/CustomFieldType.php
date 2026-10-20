<?php

namespace App\Support\People;

enum CustomFieldType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';
    case Multiselect = 'multiselect';

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
            self::Text => 'Texto corto',
            self::Textarea => 'Texto largo',
            self::Number => 'Número',
            self::Date => 'Fecha',
            self::Boolean => 'Sí / No',
            self::Select => 'Selección única',
            self::Multiselect => 'Selección múltiple',
        };
    }

    public function requiresOptions(): bool
    {
        return in_array($this, [self::Select, self::Multiselect], true);
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
