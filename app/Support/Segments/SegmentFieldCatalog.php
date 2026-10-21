<?php

namespace App\Support\Segments;

use App\Models\Church;
use App\Models\CustomFieldDefinition;
use App\Support\People\CustomFieldRegistry;
use App\Support\People\CustomFieldType;

final class SegmentFieldCatalog
{
    /**
     * @return array<int, array{
     *     source: string,
     *     key: string,
     *     label: string,
     *     type: string,
     *     options: array<int, array{value: string, label: string}>,
     *     operators: array<int, array{value: string, label: string, requiresValue: bool}>
     * }>
     */
    public static function forChurch(Church $church): array
    {
        $fields = [
            self::field('person', 'first_name', 'Nombre', 'text'),
            self::field('person', 'last_name', 'Apellido', 'text'),
            self::field('person', 'email', 'Correo', 'text'),
            self::field('person', 'phone', 'Teléfono', 'text'),
            self::field('person', 'status', 'Estado', 'select', [
                ['value' => 'active', 'label' => 'Activa'],
                ['value' => 'inactive', 'label' => 'Inactiva'],
            ]),
            self::field('person', 'created_at', 'Fecha de registro', 'date'),
        ];

        foreach (CustomFieldRegistry::activeFor($church) as $definition) {
            /** @var CustomFieldDefinition $definition */
            $type = CustomFieldType::tryFrom((string) $definition->type);

            if ($type === null) {
                continue;
            }

            $fields[] = self::field(
                'custom',
                (string) $definition->key,
                (string) $definition->label,
                self::segmentTypeFor($type),
                CustomFieldRegistry::optionChoices($definition->options),
            );
        }

        return $fields;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, mixed>|null
     */
    public static function findIn(array $fields, string $source, string $key): ?array
    {
        foreach ($fields as $field) {
            if ($field['source'] === $source && $field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(Church $church, string $source, string $key): ?array
    {
        return self::findIn(self::forChurch($church), $source, $key);
    }

    public static function segmentTypeFor(CustomFieldType $type): string
    {
        return match ($type) {
            CustomFieldType::Number => 'number',
            CustomFieldType::Date => 'date',
            CustomFieldType::Boolean => 'boolean',
            CustomFieldType::Select => 'select',
            CustomFieldType::Multiselect => 'multiselect',
            default => 'text',
        };
    }

    /**
     * @param  array<int, array{value: string, label: string}>  $options
     * @return array<string, mixed>
     */
    private static function field(
        string $source,
        string $key,
        string $label,
        string $type,
        array $options = [],
    ): array {
        return [
            'source' => $source,
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'options' => $options,
            'operators' => SegmentOperator::optionsFor($type),
        ];
    }
}
