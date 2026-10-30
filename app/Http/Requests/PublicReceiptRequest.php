<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PublicReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference_number' => trim((string) $this->input('reference_number')) ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.regex' => 'El monto debe tener como máximo dos decimales.',
            'file.mimes' => 'Adjunta una imagen JPG, PNG, WEBP o un PDF.',
            'file.max' => 'El archivo no puede superar 5 MB.',
        ];
    }
}
