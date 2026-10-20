<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Models\CustomFieldDefinition;
use App\Support\People\CustomFieldRegistry;
use App\Support\People\CustomFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCustomFieldDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', CustomFieldDefinition::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => strtolower(trim((string) $this->input('key'))),
            'label' => trim((string) $this->input('label')),
            'type' => trim((string) $this->input('type')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Church $church */
        $church = $this->route('church');

        return [
            'key' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('custom_field_definitions', 'key')
                    ->where(fn ($query) => $query->where('church_id', $church->getKey())),
            ],
            'label' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', Rule::in(CustomFieldType::values())],
            'options' => ['nullable', 'array', 'max:100'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateOptions($validator);
        });
    }

    private function validateOptions(Validator $validator): void
    {
        $type = CustomFieldType::tryFrom((string) $this->input('type'));
        $options = $this->input('options');
        $choices = CustomFieldRegistry::optionChoices(is_array($options) ? $options : null);

        if ($type?->requiresOptions()) {
            if (count($choices) === 0) {
                $validator->errors()->add('options', 'Agrega al menos una opción para este tipo de campo.');
            }

            if (is_array($options) && count($choices) !== count($options)) {
                $validator->errors()->add('options', 'Cada opción debe tener un valor válido.');
            }

            $values = array_map(fn (array $choice): string => $choice['value'], $choices);

            if (count($values) !== count(array_unique($values))) {
                $validator->errors()->add('options', 'Las opciones no pueden repetirse.');
            }
        } elseif (is_array($options) && $options !== []) {
            $validator->errors()->add('options', 'Este tipo de campo no admite opciones.');
        }
    }
}
