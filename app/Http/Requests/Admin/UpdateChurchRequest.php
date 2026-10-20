<?php

namespace App\Http\Requests\Admin;

use App\Support\Church\OnboardingOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateChurchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('platform')->check();
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
            'country_code' => Str::upper(trim((string) $this->input('country_code'))),
            'timezone' => trim((string) $this->input('timezone')),
            'locale' => trim((string) $this->input('locale')),
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
            'country_code' => ['required', 'string', Rule::in(OnboardingOptions::countryCodes())],
            'timezone' => ['required', 'string', 'timezone:all'],
            'locale' => ['required', 'string', Rule::in(OnboardingOptions::localeCodes())],
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
            'country_code.in' => 'Selecciona un país disponible.',
            'locale.in' => 'Selecciona un idioma disponible.',
            'currency_code.in' => 'Selecciona una moneda disponible.',
            'additional_currencies.*.in' => 'Una de las monedas adicionales no está disponible.',
            'additional_currencies.*.not_in' => 'La moneda principal no puede repetirse como adicional.',
            'additional_currencies.*.distinct' => 'No repitas monedas adicionales.',
            'timezone.timezone' => 'Selecciona una zona horaria válida.',
        ];
    }
}
