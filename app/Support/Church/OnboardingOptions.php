<?php

namespace App\Support\Church;

class OnboardingOptions
{
    /**
     * @return array<int, array{code: string, name: string, timezone: string, currencyCode: string}>
     */
    public static function countries(): array
    {
        return [
            [
                'code' => 'VE',
                'name' => 'Venezuela',
                'timezone' => 'America/Caracas',
                'currencyCode' => 'USD',
            ],
            [
                'code' => 'CO',
                'name' => 'Colombia',
                'timezone' => 'America/Bogota',
                'currencyCode' => 'COP',
            ],
        ];
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public static function currencies(): array
    {
        return [
            ['code' => 'USD', 'name' => 'Dólar estadounidense'],
            ['code' => 'VES', 'name' => 'Bolívar venezolano'],
            ['code' => 'COP', 'name' => 'Peso colombiano'],
            ['code' => 'EUR', 'name' => 'Euro'],
        ];
    }

    /**
     * @return array<int, array{code: string, name: string}>
     */
    public static function locales(): array
    {
        return [
            ['code' => 'es', 'name' => 'Español'],
            ['code' => 'es_VE', 'name' => 'Español (Venezuela)'],
            ['code' => 'es_CO', 'name' => 'Español (Colombia)'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function countryCodes(): array
    {
        return array_column(self::countries(), 'code');
    }

    /**
     * @return array<int, string>
     */
    public static function localeCodes(): array
    {
        return array_column(self::locales(), 'code');
    }

    /**
     * @return array<int, string>
     */
    public static function currencyCodes(): array
    {
        return array_column(self::currencies(), 'code');
    }
}
