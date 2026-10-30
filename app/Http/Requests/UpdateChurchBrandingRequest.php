<?php

namespace App\Http\Requests;

use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;

class UpdateChurchBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::ChurchSettingsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tagline' => trim((string) $this->input('tagline')) ?: null,
            'primary_color' => trim((string) $this->input('primary_color')) ?: null,
            'accent_color' => trim((string) $this->input('accent_color')) ?: null,
            'service_times' => trim((string) $this->input('service_times')) ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tagline' => ['nullable', 'string', 'max:200'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'service_times' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'El color debe tener el formato #RRGGBB.',
            'accent_color.regex' => 'El color debe tener el formato #RRGGBB.',
            'logo.image' => 'El logo debe ser una imagen.',
        ];
    }
}
