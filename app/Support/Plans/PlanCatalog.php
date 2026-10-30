<?php

namespace App\Support\Plans;

use App\Models\Church;
use App\Models\Plan;
use App\Support\Billing\Money;

final class PlanCatalog
{
    /**
     * @return array{key: string, name: string, price: string, currencyCode: string, billingInterval: string, isFree: bool}|null
     */
    public static function summary(?Plan $plan): ?array
    {
        if ($plan === null) {
            return null;
        }

        return [
            'key' => $plan->key,
            'name' => $plan->name,
            'price' => Money::format($plan->price),
            'currencyCode' => $plan->currency_code,
            'billingInterval' => $plan->billing_interval,
            'isFree' => Money::isZero($plan->price),
        ];
    }

    /**
     * @return array{key: string, name: string, price: string, currencyCode: string, billingInterval: string, isFree: bool}|null
     */
    public static function forChurch(Church $church): ?array
    {
        return self::summary(Entitlements::forChurch($church)->plan());
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     description: string,
     *     price: string,
     *     currencyCode: string,
     *     billingInterval: string,
     *     isFree: bool,
     *     isCurrent: bool,
     *     limits: array<string, ?int>,
     *     features: array<int, string>
     * }>
     */
    public static function available(?string $currentPlanKey = null): array
    {
        return Plan::query()
            ->where('is_active', true)
            ->with(['limits', 'features'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Plan $plan): array => [
                'key' => $plan->key,
                'name' => $plan->name,
                'description' => $plan->description ?? '',
                'price' => Money::format($plan->price),
                'currencyCode' => $plan->currency_code,
                'billingInterval' => $plan->billing_interval,
                'isFree' => Money::isZero($plan->price),
                'isCurrent' => $plan->key === $currentPlanKey,
                'limits' => collect(PlanResources::keys())
                    ->mapWithKeys(fn (string $resource): array => [
                        $resource => $plan->limitFor($resource)?->limit_value,
                    ])
                    ->all(),
                'features' => $plan->features->pluck('feature')->values()->all(),
            ])
            ->values()
            ->all();
    }
}
