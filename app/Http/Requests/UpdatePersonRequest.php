<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\People\CustomFieldRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $customFields = $this->input('custom_fields');

        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => $this->normalizeNullableString($this->input('last_name')),
            'email' => $this->normalizeEmail($this->input('email')),
            'phone' => $this->normalizeNullableString($this->input('phone')),
            'custom_fields' => is_array($customFields) ? $customFields : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Church $church */
        $church = $this->route('church');

        return [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            ...CustomFieldRegistry::validationRules(
                CustomFieldRegistry::activeFor($church),
            ),
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeEmail(mixed $value): ?string
    {
        $value = Str::lower(trim((string) $value));

        return $value === '' ? null : $value;
    }
}
