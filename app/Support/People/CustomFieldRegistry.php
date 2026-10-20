<?php

namespace App\Support\People;

use App\Models\Church;
use App\Models\CustomFieldDefinition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;

final class CustomFieldRegistry
{
    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    public static function activeFor(Church|int $church): Collection
    {
        $churchId = $church instanceof Church ? (int) $church->getKey() : $church;

        return CustomFieldDefinition::withoutGlobalScope('church')
            ->where('church_id', $churchId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, CustomFieldDefinition>
     */
    public static function allFor(Church|int $church): Collection
    {
        $churchId = $church instanceof Church ? (int) $church->getKey() : $church;

        return CustomFieldDefinition::withoutGlobalScope('church')
            ->where('church_id', $churchId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, CustomFieldDefinition>  $definitions
     * @return array<string, array<int, mixed>>
     */
    public static function validationRules(Collection $definitions): array
    {
        $rules = [
            'custom_fields' => ['nullable', 'array'],
        ];

        foreach ($definitions as $definition) {
            $key = 'custom_fields.'.$definition->key;
            $type = CustomFieldType::tryFrom((string) $definition->type);

            $rules[$key] = $definition->is_required
                ? ['required']
                : ['nullable'];

            $rules[$key] = array_merge($rules[$key], match ($type) {
                CustomFieldType::Text => ['string', 'max:255'],
                CustomFieldType::Textarea => ['string', 'max:5000'],
                CustomFieldType::Number => ['numeric'],
                CustomFieldType::Date => ['date_format:Y-m-d'],
                CustomFieldType::Boolean => ['boolean'],
                CustomFieldType::Select => ['string', Rule::in(self::optionValues($definition->options))],
                CustomFieldType::Multiselect => ['array', 'max:100'],
                default => ['string'],
            });

            if ($type === CustomFieldType::Multiselect) {
                $rules[$key.'.*'] = ['string', Rule::in(self::optionValues($definition->options))];
            }
        }

        return $rules;
    }

    /**
     * Merge submitted values with existing ones so deactivated definitions do
     * not lose their historical values.
     *
     * @param  Collection<int, CustomFieldDefinition>  $definitions
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    public static function normalize(Collection $definitions, array $input, array $existing = []): array
    {
        $values = $existing;

        foreach ($definitions as $definition) {
            if (! array_key_exists($definition->key, $input)) {
                continue;
            }

            $value = $input[$definition->key];

            if ($value === null || $value === '' || (is_array($value) && $value === [])) {
                unset($values[$definition->key]);

                continue;
            }

            $values[$definition->key] = self::cast(
                CustomFieldType::tryFrom((string) $definition->type),
                $value,
            );
        }

        return $values;
    }

    /**
     * @param  array<int, mixed>|null  $options
     * @return array<int, string>
     */
    public static function optionValues(?array $options): array
    {
        return collect($options ?? [])
            ->map(function (mixed $option): ?string {
                if (is_string($option)) {
                    return $option;
                }

                if (is_array($option) && isset($option['value']) && is_string($option['value'])) {
                    return $option['value'];
                }

                return null;
            })
            ->filter(fn (?string $option): bool => $option !== null && $option !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>|null  $options
     * @return array<int, array{value: string, label: string}>
     */
    public static function optionChoices(?array $options): array
    {
        return collect($options ?? [])
            ->map(function (mixed $option): ?array {
                if (is_string($option) && $option !== '') {
                    return ['value' => $option, 'label' => $option];
                }

                if (
                    is_array($option)
                    && isset($option['value'], $option['label'])
                    && is_string($option['value'])
                    && is_string($option['label'])
                    && $option['value'] !== ''
                ) {
                    return ['value' => $option['value'], 'label' => $option['label']];
                }

                return null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public static function displayValue(CustomFieldDefinition $definition, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $type = CustomFieldType::tryFrom((string) $definition->type);

        if ($type === CustomFieldType::Boolean) {
            return $value ? 'Sí' : 'No';
        }

        if ($type === CustomFieldType::Select) {
            $choice = collect(self::optionChoices($definition->options))->keyBy('value')->get((string) $value);

            return $choice['label'] ?? (string) $value;
        }

        if ($type === CustomFieldType::Multiselect) {
            $choices = collect(self::optionChoices($definition->options))->keyBy('value');

            return collect(is_array($value) ? $value : [])
                ->map(function (mixed $item) use ($choices): string {
                    $choice = $choices->get((string) $item);

                    return $choice['label'] ?? (string) $item;
                })
                ->implode(', ');
        }

        if ($type === CustomFieldType::Date && is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);

        return $encoded === false ? '—' : $encoded;
    }

    private static function cast(?CustomFieldType $type, mixed $value): mixed
    {
        return match ($type) {
            CustomFieldType::Number => is_numeric($value)
                ? (fmod((float) $value, 1.0) === 0.0 ? (int) $value : (float) $value)
                : $value,
            CustomFieldType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            CustomFieldType::Multiselect => array_values(array_unique(
                array_map(fn (mixed $item): string => (string) $item, is_array($value) ? $value : [])
            )),
            CustomFieldType::Text, CustomFieldType::Textarea, CustomFieldType::Date, CustomFieldType::Select => trim((string) $value),
            default => $value,
        };
    }
}
