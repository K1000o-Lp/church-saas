<?php

namespace App\Support\Church;

use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\Contribution;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ChurchCurrencies
{
    /**
     * @return Collection<int, ChurchCurrency>
     */
    public static function forChurch(Church $church): Collection
    {
        return ChurchCurrency::withoutGlobalScope('church')
            ->where('church_id', $church->getKey())
            ->orderByDesc('is_default')
            ->orderBy('currency_code')
            ->get();
    }

    /**
     * @param  array<int, string>  $additionalCurrencies
     * @return array{changed: bool, before: array{default: ?string, currencies: array<int, string>}, after: array{default: string, currencies: array<int, string>}}
     */
    public static function sync(
        Church $church,
        string $primaryCurrency,
        array $additionalCurrencies,
        CurrentChurch $currentChurch,
    ): array {
        $selected = collect([$primaryCurrency, ...$additionalCurrencies])
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existing = self::forChurch($church)->keyBy('currency_code');
        $before = [
            'default' => $existing->firstWhere('is_default', true)?->currency_code,
            'currencies' => $existing->keys()->sort()->values()->all(),
        ];

        foreach ($existing as $code => $currency) {
            if (in_array($code, $selected, true)) {
                continue;
            }

            if (Contribution::withoutGlobalScope('church')
                ->where('church_id', $church->getKey())
                ->where('currency_code', $code)
                ->exists()) {
                throw ValidationException::withMessages([
                    'additional_currencies' => "No puedes quitar la moneda {$code}: tiene contribuciones asociadas.",
                ]);
            }
        }

        $currentChurch->set($church);

        try {
            foreach ($existing as $code => $currency) {
                if (! in_array($code, $selected, true)) {
                    $currency->delete();

                    continue;
                }

                if ($currency->is_default) {
                    $currency->update(['is_default' => false]);
                }
            }

            foreach ($selected as $code) {
                $currency = $existing->get($code);

                if ($currency === null) {
                    ChurchCurrency::create([
                        'currency_code' => $code,
                        'is_default' => $code === $primaryCurrency,
                    ]);

                    continue;
                }

                if ($code === $primaryCurrency) {
                    $currency->update(['is_default' => true]);
                }
            }
        } finally {
            $currentChurch->clear();
        }

        $after = [
            'default' => $primaryCurrency,
            'currencies' => collect($selected)->sort()->values()->all(),
        ];

        return [
            'changed' => $before !== $after,
            'before' => $before,
            'after' => $after,
        ];
    }
}
