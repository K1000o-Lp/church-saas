<?php

namespace App\Http\Requests\Admin;

use App\Support\Church\ChurchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateChurchStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('platform')->check();
    }

    protected function prepareForValidation(): void
    {
        $reason = trim((string) $this->input('reason'));

        $this->merge([
            'status' => trim((string) $this->input('status')),
            'reason' => $reason === '' ? null : $reason,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ChurchStatus::all())],
            'reason' => [
                'nullable',
                'string',
                'max:500',
                Rule::requiredIf(fn (): bool => $this->input('status') !== ChurchStatus::ACTIVE),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Selecciona un estado válido.',
            'reason.required' => 'Indica el motivo del cambio de estado.',
        ];
    }
}
