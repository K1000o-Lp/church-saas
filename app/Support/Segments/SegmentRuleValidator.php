<?php

namespace App\Support\Segments;

use App\Models\Church;
use Illuminate\Validation\ValidationException;

final class SegmentRuleValidator
{
    public const MAX_CONDITIONS = 20;

    /**
     * @param  array<string, mixed>  $rules
     * @return array{version: int, match: string, conditions: array<int, array<string, mixed>>}
     *
     * @throws ValidationException
     */
    public static function validate(array $rules, Church $church): array
    {
        $fields = SegmentFieldCatalog::forChurch($church);
        $match = $rules['match'] ?? 'all';

        if (! is_string($match) || ! in_array($match, ['all', 'any'], true)) {
            throw self::error('Indica si deben cumplirse todas o alguna condición.');
        }

        $conditions = $rules['conditions'] ?? null;

        if (! is_array($conditions) || $conditions === []) {
            throw self::error('Agrega al menos una condición al segmento.');
        }

        if (count($conditions) > self::MAX_CONDITIONS) {
            throw self::error('El segmento admite hasta '.self::MAX_CONDITIONS.' condiciones.');
        }

        $normalized = [];

        foreach (array_values($conditions) as $index => $condition) {
            $normalized[] = self::condition($condition, $fields, $index + 1);
        }

        return [
            'version' => 1,
            'match' => $match,
            'conditions' => $normalized,
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public static function summary(array $rules, Church $church): string
    {
        $fields = SegmentFieldCatalog::forChurch($church);
        $conditions = $rules['conditions'] ?? null;

        if (! is_array($conditions) || $conditions === []) {
            return 'Sin condiciones';
        }

        $parts = [];

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            $source = $condition['source'] ?? '';
            $key = $condition['field'] ?? '';
            $operatorValue = $condition['operator'] ?? '';

            if (! is_string($source) || ! is_string($key) || ! is_string($operatorValue)) {
                continue;
            }

            $operator = SegmentOperator::tryFrom($operatorValue);
            $field = SegmentFieldCatalog::findIn($fields, $source, $key);

            if ($field === null || $operator === null) {
                $parts[] = $key !== '' ? $key : 'Condición no disponible';

                continue;
            }

            $text = $field['label'].' '.$operator->label();

            if ($operator->requiresValue()) {
                $text .= ' '.self::displayValue($field, $condition['value'] ?? null);
            }

            $parts[] = $text;
        }

        if ($parts === []) {
            return 'Sin condiciones';
        }

        $separator = ($rules['match'] ?? 'all') === 'any' ? ' o ' : ' y ';

        return implode($separator, $parts);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    private static function condition(mixed $condition, array $fields, int $position): array
    {
        if (! is_array($condition)) {
            throw self::error('La condición '.$position.' no es válida.');
        }

        $source = $condition['source'] ?? '';
        $key = $condition['field'] ?? '';
        $operatorValue = $condition['operator'] ?? '';

        if (! is_string($source) || ! in_array($source, ['person', 'custom'], true)) {
            throw self::error('La condición '.$position.' tiene un origen de campo inválido.');
        }

        if (! is_string($key) || $key === '') {
            throw self::error('La condición '.$position.' necesita un campo válido.');
        }

        $field = SegmentFieldCatalog::findIn($fields, $source, $key);

        if ($field === null) {
            throw self::error('El campo "'.$key.'" no está disponible. Actualiza el segmento.');
        }

        $operator = is_string($operatorValue) ? SegmentOperator::tryFrom($operatorValue) : null;

        if ($operator === null || ! SegmentOperator::allows($field['type'], $operator->value)) {
            throw self::error('El operador de la condición '.$position.' no es válido para este campo.');
        }

        return [
            'source' => $source,
            'field' => $key,
            'operator' => $operator->value,
            'value' => $operator->requiresValue()
                ? self::normalizeValue($field, $operator, $condition['value'] ?? null, $position)
                : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function normalizeValue(
        array $field,
        SegmentOperator $operator,
        mixed $value,
        int $position,
    ): mixed {
        return match ($field['type']) {
            'text' => self::textValue($operator, $value, $position),
            'number' => self::numberValue($operator, $value, $position),
            'date' => self::dateValue($operator, $value, $position),
            'select' => self::selectValue($field, $operator, $value, $position),
            'multiselect' => self::multiSelectValue($field, $operator, $value, $position),
            default => null,
        };
    }

    private static function textValue(SegmentOperator $operator, mixed $value, int $position): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw self::error('La condición '.$position.' necesita un valor de texto.');
        }

        $value = trim($value);

        if (mb_strlen($value) > 255) {
            throw self::error('El valor de la condición '.$position.' es demasiado largo.');
        }

        return $value;
    }

    private static function numberValue(SegmentOperator $operator, mixed $value, int $position): int|float|array
    {
        if ($operator === SegmentOperator::Between) {
            if (! is_array($value) || count($value) !== 2) {
                throw self::error('La condición '.$position.' necesita dos números para el rango.');
            }

            [$from, $to] = array_values($value);

            if (! is_numeric($from) || ! is_numeric($to)) {
                throw self::error('La condición '.$position.' necesita números válidos.');
            }

            $from = self::number($from);
            $to = self::number($to);

            return [$from <= $to ? $from : $to, $from <= $to ? $to : $from];
        }

        if (! is_numeric($value)) {
            throw self::error('La condición '.$position.' necesita un número válido.');
        }

        return self::number($value);
    }

    private static function dateValue(SegmentOperator $operator, mixed $value, int $position): string|array
    {
        if ($operator === SegmentOperator::Between) {
            if (! is_array($value) || count($value) !== 2) {
                throw self::error('La condición '.$position.' necesita dos fechas para el rango.');
            }

            [$from, $to] = array_values($value);

            if (! self::isDate($from) || ! self::isDate($to)) {
                throw self::error('La condición '.$position.' necesita fechas válidas.');
            }

            return [min($from, $to), max($from, $to)];
        }

        if (! self::isDate($value)) {
            throw self::error('La condición '.$position.' necesita una fecha válida.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function selectValue(
        array $field,
        SegmentOperator $operator,
        mixed $value,
        int $position,
    ): string|array {
        $allowed = array_map(fn (array $option): string => $option['value'], $field['options']);

        if ($operator === SegmentOperator::AnyOf) {
            if (! is_array($value) || $value === []) {
                throw self::error('La condición '.$position.' necesita al menos una opción.');
            }

            $values = self::stringList($value, $position);

            if (array_diff($values, $allowed) !== []) {
                throw self::error('La condición '.$position.' contiene una opción no válida.');
            }

            return $values;
        }

        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw self::error('La condición '.$position.' necesita una opción válida.');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array<int, string>
     */
    private static function multiSelectValue(
        array $field,
        SegmentOperator $operator,
        mixed $value,
        int $position,
    ): array {
        $allowed = array_map(fn (array $option): string => $option['value'], $field['options']);

        if (! is_array($value) || $value === []) {
            throw self::error('La condición '.$position.' necesita al menos una opción.');
        }

        $values = self::stringList($value, $position);

        if (array_diff($values, $allowed) !== []) {
            throw self::error('La condición '.$position.' contiene una opción no válida.');
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function displayValue(array $field, mixed $value): string
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): string => self::optionLabel($field, $item))
                ->implode(', ');
        }

        return self::optionLabel($field, $value);
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function optionLabel(array $field, mixed $value): string
    {
        if ($field['type'] === 'select' || $field['type'] === 'multiselect') {
            foreach ($field['options'] as $option) {
                if ($option['value'] === (string) $value) {
                    return $option['label'];
                }
            }
        }

        return is_scalar($value) ? (string) $value : '—';
    }

    /**
     * @param  array<int, mixed>  $value
     * @return array<int, string>
     */
    private static function stringList(array $value, int $position): array
    {
        $values = [];

        foreach ($value as $item) {
            if (! is_string($item) || $item === '') {
                throw self::error('La condición '.$position.' contiene una opción no válida.');
            }

            $values[] = $item;
        }

        return array_values(array_unique($values));
    }

    private static function number(mixed $value): int|float
    {
        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }

    private static function isDate(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    private static function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['rules' => $message]);
    }
}
