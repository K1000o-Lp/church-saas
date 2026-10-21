<?php

namespace App\Http\Requests;

use App\Models\Church;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateChurchPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $church = $this->route('church');

        return $church instanceof Church && Gate::allows('managePlan', $church);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'plan' => trim((string) $this->input('plan')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan' => [
                'required',
                'string',
                Rule::exists('plans', 'key')->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan.required' => 'Selecciona un plan.',
            'plan.exists' => 'Selecciona un plan activo.',
        ];
    }
}
