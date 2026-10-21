<?php

namespace App\Support\Plans;

use App\Exceptions\PlanLimitExceededException;
use App\Models\Church;
use App\Models\ChurchFeatureOverride;
use App\Models\ChurchLimitOverride;
use App\Models\ChurchSubscription;
use App\Models\Plan;

final class Entitlements
{
    /**
     * @param  array<string, ?int>  $limits
     * @param  array<string, string>  $limitSources
     * @param  array<string, bool>  $features
     * @param  array<string, string>  $featureSources
     */
    private function __construct(
        private readonly int $churchId,
        private readonly ?Plan $plan,
        private readonly ?ChurchSubscription $subscription,
        private readonly array $limits,
        private readonly array $limitSources,
        private readonly array $features,
        private readonly array $featureSources,
    ) {}

    public static function forChurch(Church|int $church): self
    {
        return self::forChurchId($church instanceof Church ? (int) $church->getKey() : $church);
    }

    public static function forChurchId(int $churchId): self
    {
        $subscription = ChurchSubscription::query()
            ->with(['plan.limits', 'plan.features'])
            ->where('church_id', $churchId)
            ->first();

        $plan = $subscription?->plan ?? PlanMatrix::defaultPlan()?->load(['limits', 'features']);

        $limitOverrides = ChurchLimitOverride::query()
            ->where('church_id', $churchId)
            ->get()
            ->keyBy('resource');

        $featureOverrides = ChurchFeatureOverride::query()
            ->where('church_id', $churchId)
            ->get()
            ->keyBy('feature');

        $limits = [];
        $limitSources = [];

        foreach (PlanResources::keys() as $resource) {
            $planLimit = $plan?->limitFor($resource);

            $limits[$resource] = $planLimit?->limit_value;
            $limitSources[$resource] = 'plan';

            if ($limitOverrides->has($resource)) {
                $limits[$resource] = $limitOverrides->get($resource)->limit_value;
                $limitSources[$resource] = 'override';
            } elseif ($plan === null) {
                $limits[$resource] = 0;
            }
        }

        $planFeatures = $plan?->features->pluck('feature')->all() ?? [];

        $features = [];
        $featureSources = [];

        foreach (PlanFeatures::keys() as $feature) {
            $features[$feature] = in_array($feature, $planFeatures, true);
            $featureSources[$feature] = 'plan';

            if ($featureOverrides->has($feature)) {
                $features[$feature] = (bool) $featureOverrides->get($feature)->is_enabled;
                $featureSources[$feature] = 'override';
            }
        }

        return new self(
            $churchId,
            $plan,
            $subscription,
            $limits,
            $limitSources,
            $features,
            $featureSources,
        );
    }

    public function churchId(): int
    {
        return $this->churchId;
    }

    public function plan(): ?Plan
    {
        return $this->plan;
    }

    public function subscription(): ?ChurchSubscription
    {
        return $this->subscription;
    }

    public function limit(string $resource): ?int
    {
        return array_key_exists($resource, $this->limits) ? $this->limits[$resource] : 0;
    }

    public function limitSource(string $resource): string
    {
        return $this->limitSources[$resource] ?? 'plan';
    }

    public function isUnlimited(string $resource): bool
    {
        return $this->limit($resource) === null;
    }

    /**
     * @return array<string, ?int>
     */
    public function limits(): array
    {
        return $this->limits;
    }

    public function can(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    public function featureSource(string $feature): string
    {
        return $this->featureSources[$feature] ?? 'plan';
    }

    /**
     * @return array<string, bool>
     */
    public function features(): array
    {
        return $this->features;
    }

    /**
     * @return array<string, mixed>
     */
    public function usageSummary(): array
    {
        $usage = ChurchUsage::forChurchId($this->churchId);

        return collect(PlanResources::keys())
            ->mapWithKeys(function (string $resource) use ($usage): array {
                $used = $usage[$resource] ?? 0;
                $limit = $this->limit($resource);

                return [
                    $resource => [
                        ...PlanResources::definition($resource),
                        'key' => $resource,
                        'used' => $used,
                        'limit' => $limit,
                        'remaining' => $limit === null ? null : max(0, $limit - $used),
                        'unlimited' => $limit === null,
                        'exceeded' => $limit !== null && $used > $limit,
                        'source' => $this->limitSource($resource),
                    ],
                ];
            })
            ->all();
    }

    public function ensureCanCreate(string $resource, int $additional = 1): void
    {
        $limit = $this->limit($resource);

        if ($limit === null) {
            return;
        }

        $used = ChurchUsage::count($this->churchId, $resource);

        if ($used + $additional > $limit) {
            throw new PlanLimitExceededException($resource, $limit, $used);
        }
    }

    public function usageFor(string $resource): int
    {
        return ChurchUsage::count($this->churchId, $resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plan' => $this->plan === null ? null : [
                'key' => $this->plan->key,
                'name' => $this->plan->name,
                'description' => $this->plan->description,
                'priceMinor' => $this->plan->price_minor,
                'currencyCode' => $this->plan->currency_code,
                'billingInterval' => $this->plan->billing_interval,
                'isFree' => $this->plan->price_minor === 0,
            ],
            'subscription' => $this->subscription === null ? null : [
                'status' => $this->subscription->status,
                'statusLabel' => SubscriptionStatus::label($this->subscription->status),
                'startedAt' => $this->subscription->started_at?->toIso8601String(),
                'planChangedAt' => $this->subscription->plan_changed_at?->toIso8601String(),
                'endsAt' => $this->subscription->ends_at?->toIso8601String(),
                'notes' => $this->subscription->notes,
            ],
            'usage' => $this->usageSummary(),
            'features' => collect(PlanFeatures::keys())
                ->map(fn (string $feature): array => [
                    ...PlanFeatures::definition($feature),
                    'key' => $feature,
                    'enabled' => $this->can($feature),
                    'source' => $this->featureSource($feature),
                ])
                ->values()
                ->all(),
        ];
    }
}
