<?php

namespace App\Support\Segments;

enum SegmentOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case IsEmpty = 'is_empty';
    case IsNotEmpty = 'is_not_empty';
    case GreaterThan = 'greater_than';
    case GreaterOrEqual = 'greater_or_equal';
    case LessThan = 'less_than';
    case LessOrEqual = 'less_or_equal';
    case Between = 'between';
    case Before = 'before';
    case After = 'after';
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';
    case Is = 'is';
    case IsNot = 'is_not';
    case AnyOf = 'any_of';
    case ContainsAny = 'contains_any';
    case ContainsAll = 'contains_all';

    public function label(): string
    {
        return match ($this) {
            self::Equals => 'es igual a',
            self::NotEquals => 'no es igual a',
            self::Contains => 'contiene',
            self::NotContains => 'no contiene',
            self::StartsWith => 'empieza por',
            self::IsEmpty => 'está vacío',
            self::IsNotEmpty => 'tiene valor',
            self::GreaterThan => 'es mayor que',
            self::GreaterOrEqual => 'es mayor o igual que',
            self::LessThan => 'es menor que',
            self::LessOrEqual => 'es menor o igual que',
            self::Between => 'está entre',
            self::Before => 'es anterior a',
            self::After => 'es posterior a',
            self::IsTrue => 'es verdadero',
            self::IsFalse => 'es falso',
            self::Is => 'es',
            self::IsNot => 'no es',
            self::AnyOf => 'es alguno de',
            self::ContainsAny => 'contiene alguno de',
            self::ContainsAll => 'contiene todos',
        };
    }

    public function requiresValue(): bool
    {
        return ! in_array($this, [
            self::IsEmpty,
            self::IsNotEmpty,
            self::IsTrue,
            self::IsFalse,
        ], true);
    }

    /**
     * @return array<int, self>
     */
    public static function forFieldType(string $type): array
    {
        return match ($type) {
            'text' => [
                self::Equals,
                self::NotEquals,
                self::Contains,
                self::NotContains,
                self::StartsWith,
                self::IsEmpty,
                self::IsNotEmpty,
            ],
            'number' => [
                self::Equals,
                self::NotEquals,
                self::GreaterThan,
                self::GreaterOrEqual,
                self::LessThan,
                self::LessOrEqual,
                self::Between,
            ],
            'date' => [
                self::Before,
                self::After,
                self::Between,
            ],
            'boolean' => [
                self::IsTrue,
                self::IsFalse,
            ],
            'select' => [
                self::Is,
                self::IsNot,
                self::AnyOf,
                self::IsEmpty,
                self::IsNotEmpty,
            ],
            'multiselect' => [
                self::ContainsAny,
                self::ContainsAll,
                self::IsEmpty,
                self::IsNotEmpty,
            ],
            default => [],
        };
    }

    /**
     * @return array<int, array{value: string, label: string, requiresValue: bool}>
     */
    public static function optionsFor(string $type): array
    {
        return array_map(fn (self $operator): array => [
            'value' => $operator->value,
            'label' => $operator->label(),
            'requiresValue' => $operator->requiresValue(),
        ], self::forFieldType($type));
    }

    public static function allows(string $type, string $operator): bool
    {
        $candidate = self::tryFrom($operator);

        return $candidate !== null && in_array($candidate, self::forFieldType($type), true);
    }
}
