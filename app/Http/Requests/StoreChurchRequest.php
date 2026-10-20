<?php

namespace App\Http\Requests;

use App\Support\Church\OnboardingOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreChurchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $additionalCurrencies = collect($this->input('additional_currencies', []))
            ->map(fn (mixed $currency): string => Str::upper(trim((string) $currency)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => Str::slug((string) $this->input('slug')),
            'country_code' => Str::upper(trim((string) $this->input('country_code'))),
            'timezone' => trim((string) $this->input('timezone')),
            'currency_code' => Str::upper(trim((string) $this->input('currency_code'))),
            'additional_currencies' => $additionalCurrencies,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:churches,slug'],
            'country_code' => ['required', 'string', Rule::in(OnboardingOptions::countryCodes())],
            'timezone' => ['required', 'string', 'timezone:all'],
            'currency_code' => ['required', 'string', Rule::in(OnboardingOptions::currencyCodes())],
            'additional_currencies' => ['sometimes', 'array', 'max:10'],
            'additional_currencies.*' => [
                'string',
                'distinct',
                Rule::in(OnboardingOptions::currencyCodes()),
                Rule::notIn([(string) $this->input('currency_code')]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.unique' => 'Ya existe una iglesia con esa dirección.',
            'slug.alpha_dash' => 'La dirección solo puede contener letras, números y guiones.',
            'country_code.in' => 'Selecciona un país disponible.',
            'currency_code.in' => 'Selecciona una moneda disponible.',
            'additional_currencies.*.in' => 'Una de las monedas adicionales no está disponible.',
            'additional_currencies.*.not_in' => 'La moneda principal no puede repetirse como adicional.',
            'additional_currencies.*.distinct' => 'No repitas monedas adicionales.',
            'timezone.timezone' => 'Selecciona una zona horaria válida.',
        ];
    }
}
