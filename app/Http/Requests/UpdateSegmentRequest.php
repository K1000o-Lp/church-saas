<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Models\Segment;
use App\Support\Segments\SegmentRuleValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateSegmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $segment = $this->segment();

        return $segment !== null && Gate::allows('update', $segment);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => trim((string) $this->input('description')) ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Church $church */
        $church = $this->route('church');
        $segment = $this->segment();

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('segments', 'name')
                    ->where(fn ($query) => $query->where('church_id', $church->getKey()))
                    ->ignore($segment?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'rules' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Church|null $church */
            $church = $this->route('church');
            $segment = $this->segment();

            if (! $church instanceof Church || ! $segment instanceof Segment) {
                return;
            }

            if (! $segment->isDynamic()) {
                if ($this->input('rules') !== null && $this->input('rules') !== []) {
                    $validator->errors()->add('rules', 'Un segmento estático no usa reglas.');
                }

                return;
            }

            $rules = $this->input('rules');

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
        });
    }

    private function segment(): ?Segment
    {
        $segment = $this->route('segment');

        if ($segment instanceof Segment) {
            return $segment;
        }

        if (! is_string($segment) && ! is_int($segment)) {
            return null;
        }

        if (! ctype_digit((string) $segment)) {
            return null;
        }

        return Segment::query()->find($segment);
    }
}
