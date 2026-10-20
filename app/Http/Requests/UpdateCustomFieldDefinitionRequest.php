<?php

namespace App\Http\Requests;

use App\Models\CustomFieldDefinition;
use App\Support\People\CustomFieldRegistry;
use App\Support\People\CustomFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCustomFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'label' => trim((string) $this->input('label')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'options' => ['nullable', 'array', 'max:100'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $definition = $this->route('definition');

            if (! $definition instanceof CustomFieldDefinition) {
                return;
            }

            $type = CustomFieldType::tryFrom((string) $definition->type);
            $options = $this->input('options');

            if (! is_array($options)) {
                return;
            }

            $choices = CustomFieldRegistry::optionChoices($options);

            if ($type?->requiresOptions()) {
                if (count($choices) === 0) {
                    $validator->errors()->add('options', 'Agrega al menos una opción para este tipo de campo.');
                }

                if (count($choices) !== count($options)) {
                    $validator->errors()->add('options', 'Cada opción debe tener un valor válido.');
                }

                $values = array_map(fn (array $choice): string => $choice['value'], $choices);

                if (count($values) !== count(array_unique($values))) {
                    $validator->errors()->add('options', 'Las opciones no pueden repetirse.');
                }
            } elseif ($options !== []) {
                $validator->errors()->add('options', 'Este tipo de campo no admite opciones.');
            }
        });
    }
}
