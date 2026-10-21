<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Models\Segment;
use App\Support\Segments\SegmentRuleValidator;
use App\Support\Segments\SegmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StoreSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Segment::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => trim((string) $this->input('description')) ?: null,
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
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('segments', 'name')
                    ->where(fn ($query) => $query->where('church_id', $church->getKey())),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', 'string', Rule::in(SegmentType::values())],
            'rules' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Church|null $church */
            $church = $this->route('church');

            if (! $church instanceof Church) {
                return;
            }

            $type = SegmentType::tryFrom((string) $this->input('type'));
            $rules = $this->input('rules');

            if ($type === SegmentType::Dynamic) {
                if (! is_array($rules)) {
                    $validator->errors()->add('rules', 'Define al menos una condición para el segmento.');

                    return;
                }

                try {
                    SegmentRuleValidator::validate($rules, $church);
                } catch (ValidationException $exception) {
                    foreach ($exception->errors() as $messages) {
                        foreach ($messages as $message) {
                            $validator->errors()->add('rules', $message);
                        }
                    }
                }

                return;
            }

            if ($rules !== null && $rules !== []) {
                $validator->errors()->add('rules', 'Un segmento estático no usa reglas.');
            }
        });
    }
}
