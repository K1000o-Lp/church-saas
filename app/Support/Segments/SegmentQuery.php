<?php

namespace App\Support\Segments;

use App\Models\Church;
use App\Models\Person;
use Illuminate\Database\Eloquent\Builder;

final class SegmentQuery
{
    /**
     * @param  array<string, mixed>  $rules
     */
    public static function matchingPeople(Church $church, array $rules): Builder
    {
        $query = Person::query()->where('status', '!=', 'archived');

        $conditions = $rules['conditions'] ?? null;

        if (! is_array($conditions) || $conditions === []) {
            return $query;
        }

        $matchAny = ($rules['match'] ?? 'all') === 'any';
        $fields = SegmentFieldCatalog::forChurch($church);

        $query->where(function (Builder $nested) use ($conditions, $fields, $matchAny): void {
            foreach ($conditions as $condition) {
                if (! is_array($condition)) {
                    $nested->whereRaw('1 = 0');

                    continue;
                }

                $source = $condition['source'] ?? '';
                $key = $condition['field'] ?? '';

                if (! is_string($source) || ! is_string($key)) {
                    $nested->whereRaw('1 = 0');

                    continue;
                }

                $field = SegmentFieldCatalog::findIn($fields, $source, $key);

                if ($field === null) {
                    $nested->whereRaw('1 = 0');

                    continue;
                }

                $apply = function (Builder $inner) use ($field, $condition): void {
                    self::applyCondition($inner, $field, $condition);
                };

                if ($matchAny) {
                    $nested->orWhere($apply);
                } else {
                    $nested->where($apply);
                }
            }
        });

        return $query;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public static function count(Church $church, array $rules): int
    {
        return self::matchingPeople($church, $rules)->count();
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $condition
     */
    private static function applyCondition(Builder $query, array $field, array $condition): void
    {
        $operatorValue = $condition['operator'] ?? '';
        $operator = is_string($operatorValue) ? SegmentOperator::tryFrom($operatorValue) : null;

        if ($operator === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $value = $condition['value'] ?? null;

        if ($field['source'] === 'person') {
            self::applyPersonCondition($query, (string) $field['key'], (string) $field['type'], $operator, $value);

            return;
        }

        self::applyCustomCondition($query, (string) $field['key'], (string) $field['type'], $operator, $value);
    }

    private static function applyPersonCondition(
        Builder $query,
        string $column,
        string $type,
        SegmentOperator $operator,
        mixed $value,
    ): void {
        $column = 'people.'.$column;

        if ($type === 'text') {
            self::applyText($query, $column, $operator, $value);

            return;
        }

        if ($type === 'select') {
            match ($operator) {
                SegmentOperator::Is => $query->where($column, $value),
                SegmentOperator::IsNot => $query->whereRaw("{$column} IS DISTINCT FROM ?", [$value]),
                SegmentOperator::AnyOf => $query->whereIn($column, (array) $value),
                SegmentOperator::IsEmpty => $query->where(fn (Builder $inner) => $inner->whereNull($column)->orWhere($column, '')),
                SegmentOperator::IsNotEmpty => $query->whereRaw("{$column} IS NOT NULL AND {$column} <> ''"),
                default => $query->whereRaw('1 = 0'),
            };

            return;
        }

        if ($type === 'date') {
            match ($operator) {
                SegmentOperator::Before => $query->whereDate($column, '<', (string) $value),
                SegmentOperator::After => $query->whereDate($column, '>', (string) $value),
                SegmentOperator::Between => $query
                    ->whereDate($column, '>=', (string) $value[0])
                    ->whereDate($column, '<=', (string) $value[1]),
                default => $query->whereRaw('1 = 0'),
            };

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private static function applyCustomCondition(
        Builder $query,
        string $key,
        string $type,
        SegmentOperator $operator,
        mixed $value,
    ): void {
        $json = self::jsonPath($key);
        $text = self::textPath($key);

        if ($type === 'text') {
            self::applyText($query, $text, $operator, $value);

            return;
        }

        if ($type === 'number') {
            match ($operator) {
                SegmentOperator::Equals => $query->whereRaw(
                    "jsonb_typeof({$json}) = 'number' AND ({$text})::numeric = ?",
                    [$value],
                ),
                SegmentOperator::NotEquals => $query->whereRaw(
                    "jsonb_typeof({$json}) = 'number' AND ({$text})::numeric <> ?",
                    [$value],
                ),
                SegmentOperator::GreaterThan => $query->whereRaw(
                    "jsonb_typeof({$json}) = 'number' AND ({$text})::numeric > ?",
                    [$value],
                ),
                SegmentOperator::GreaterOrEqual => $query->whereRaw(
                    "jsonb_typeof({$json}) = 'number' AND ({$text})::numeric >= ?",
                    [$value],
                ),
                SegmentOperator::LessThan => $query->whereRaw(
                    "jsonb_typeof({$json}) = 'number' AND ({$text})::numeric < ?",
                    [$value],
                ),
                SegmentOperator::LessOrEqual => $query->whereRaw(
                    "jsonb_typeof({$json}) = 'number' AND ({$text})::numeric <= ?",
                    [$value],
                ),
                SegmentOperator::Between => $query->whereRaw(
                    "jsonb_typeof({$json}) = 'number' AND ({$text})::numeric BETWEEN ? AND ?",
                    [$value[0], $value[1]],
                ),
                default => $query->whereRaw('1 = 0'),
            };

            return;
        }

        if ($type === 'date') {
            $guard = "jsonb_typeof({$json}) = 'string' AND {$text} ~ '^\\d{4}-\\d{2}-\\d{2}$'";

            match ($operator) {
                SegmentOperator::Before => $query->whereRaw(
                    "{$guard} AND ({$text})::date < ?",
                    [(string) $value],
                ),
                SegmentOperator::After => $query->whereRaw(
                    "{$guard} AND ({$text})::date > ?",
                    [(string) $value],
                ),
                SegmentOperator::Between => $query->whereRaw(
                    "{$guard} AND ({$text})::date BETWEEN ? AND ?",
                    [(string) $value[0], (string) $value[1]],
                ),
                default => $query->whereRaw('1 = 0'),
            };

            return;
        }

        if ($type === 'boolean') {
            match ($operator) {
                SegmentOperator::IsTrue => $query->whereRaw(
                    "jsonb_typeof({$json}) = 'boolean' AND ({$text})::boolean = true",
                ),
                SegmentOperator::IsFalse => $query->whereRaw(
                    "jsonb_typeof({$json}) = 'boolean' AND ({$text})::boolean = false",
                ),
                default => $query->whereRaw('1 = 0'),
            };

            return;
        }

        if ($type === 'select') {
            match ($operator) {
                SegmentOperator::Is => $query->whereRaw(
                    'custom_fields @> ?::jsonb',
                    [self::json([$key => $value])],
                ),
                SegmentOperator::IsNot => $query->whereRaw(
                    'NOT (custom_fields @> ?::jsonb)',
                    [self::json([$key => $value])],
                ),
                SegmentOperator::AnyOf => $query->where(function (Builder $inner) use ($key, $value): void {
                    foreach ((array) $value as $option) {
                        $inner->orWhereRaw('custom_fields @> ?::jsonb', [self::json([$key => $option])]);
                    }
                }),
                SegmentOperator::IsEmpty => $query->whereRaw(
                    "({$text} IS NULL OR {$text} = '')",
                ),
                SegmentOperator::IsNotEmpty => $query->whereRaw(
                    "({$text} IS NOT NULL AND {$text} <> '')",
                ),
                default => $query->whereRaw('1 = 0'),
            };

            return;
        }

        if ($type === 'multiselect') {
            match ($operator) {
                SegmentOperator::ContainsAny => $query->where(function (Builder $inner) use ($key, $value): void {
                    foreach ((array) $value as $option) {
                        $inner->orWhereRaw('custom_fields @> ?::jsonb', [self::json([$key => [$option]])]);
                    }
                }),
                SegmentOperator::ContainsAll => $query->whereRaw(
                    'custom_fields @> ?::jsonb',
                    [self::json([$key => array_values((array) $value)])],
                ),
                SegmentOperator::IsEmpty => $query->whereRaw(
                    "({$text} IS NULL OR {$json} = '[]'::jsonb)",
                ),
                SegmentOperator::IsNotEmpty => $query->whereRaw(
                    "({$text} IS NOT NULL AND {$json} <> '[]'::jsonb)",
                ),
                default => $query->whereRaw('1 = 0'),
            };

            return;
        }

        $query->whereRaw('1 = 0');
    }

    private static function applyText(
        Builder $query,
        string $expression,
        SegmentOperator $operator,
        mixed $value,
    ): void {
        $lower = 'lower('.$expression.')';

        match ($operator) {
            SegmentOperator::Equals => $query->whereRaw(
                $lower.' = ?',
                [mb_strtolower((string) $value)],
            ),
            SegmentOperator::NotEquals => $query->whereRaw(
                $lower.' IS DISTINCT FROM ?',
                [mb_strtolower((string) $value)],
            ),
            SegmentOperator::Contains => $query->whereRaw(
                $lower.' LIKE ?',
                ['%'.self::like((string) $value).'%'],
            ),
            SegmentOperator::NotContains => $query->whereRaw(
                'coalesce('.$lower.", '') NOT LIKE ?",
                ['%'.self::like((string) $value).'%'],
            ),
            SegmentOperator::StartsWith => $query->whereRaw(
                $lower.' LIKE ?',
                [self::like((string) $value).'%'],
            ),
            SegmentOperator::IsEmpty => $query->whereRaw(
                "({$expression} IS NULL OR {$expression} = '')",
            ),
            SegmentOperator::IsNotEmpty => $query->whereRaw(
                "({$expression} IS NOT NULL AND {$expression} <> '')",
            ),
            default => $query->whereRaw('1 = 0'),
        };
    }

    private static function jsonPath(string $key): string
    {
        return "custom_fields->'".self::quoteKey($key)."'";
    }

    private static function textPath(string $key): string
    {
        return "custom_fields->>'".self::quoteKey($key)."'";
    }

    private static function quoteKey(string $key): string
    {
        return str_replace("'", "''", $key);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private static function json(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '{}' : $encoded;
    }

    private static function like(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($value));
    }
}
