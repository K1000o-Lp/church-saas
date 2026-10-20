<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateChurchFeatureOverridesRequest;
use App\Http\Requests\Admin\UpdateChurchLimitOverridesRequest;
use App\Http\Requests\Admin\UpdateChurchSubscriptionRequest;
use App\Models\Church;
use App\Models\ChurchFeatureOverride;
use App\Models\ChurchLimitOverride;
use App\Models\ChurchSubscription;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Support\Audit\PlatformAudit;
use App\Support\Church\ChurchStatus;
use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanFeatures;
use App\Support\Plans\PlanResources;
use App\Support\Plans\SubscriptionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChurchPlanController extends Controller
{
    public function show(Church $church): Response
    {
        $church->load(['subscription.plan.limits', 'subscription.plan.features', 'subscription.assignedBy']);

        $entitlements = Entitlements::forChurch($church);
        $limitOverrides = ChurchLimitOverride::query()
            ->where('church_id', $church->getKey())
            ->get()
            ->keyBy('resource');
        $featureOverrides = ChurchFeatureOverride::query()
            ->where('church_id', $church->getKey())
            ->get()
            ->keyBy('feature');
        $usage = $entitlements->usageSummary();
        $plan = $entitlements->plan();
        $subscription = $entitlements->subscription();

        return Inertia::render('admin/churches/plan', [
            'church' => [
                'id' => $church->getKey(),
                'name' => $church->name,
                'slug' => $church->slug,
                'status' => $church->status,
                'statusLabel' => ChurchStatus::label($church->status),
            ],
            'subscription' => [
                'planId' => $subscription?->plan_id,
                'planKey' => $plan?->key,
                'planName' => $plan?->name,
                'status' => $subscription?->status,
                'statusLabel' => $subscription === null
                    ? null
                    : SubscriptionStatus::label($subscription->status),
                'startedAt' => $subscription?->started_at?->toIso8601String(),
                'planChangedAt' => $subscription?->plan_changed_at?->toIso8601String(),
                'endsAt' => $subscription?->ends_at?->toIso8601String(),
                'notes' => $subscription?->notes,
                'assignedBy' => $subscription?->assignedBy?->name,
            ],
            'plans' => Plan::query()
                ->where('is_active', true)
                ->with(['limits', 'features'])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->getKey(),
                    'key' => $plan->key,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'priceMinor' => $plan->price_minor,
                    'currencyCode' => $plan->currency_code,
                    'billingInterval' => $plan->billing_interval,
                    'limits' => $plan->limits->mapWithKeys(fn ($limit): array => [
                        $limit->resource => $limit->limit_value,
                    ])->all(),
                    'features' => $plan->features->pluck('feature')->values()->all(),
                ])
                ->values(),
            'resources' => collect(PlanResources::keys())
                ->map(function (string $resource) use ($usage, $limitOverrides, $plan): array {
                    $override = $limitOverrides->get($resource);
                    $summary = $usage[$resource];

                    return [
                        ...$summary,
                        'planLimit' => $plan?->limitFor($resource)?->limit_value,
                        'override' => [
                            'exists' => $override !== null,
                            'mode' => $override === null
                                ? 'inherit'
                                : ($override->limit_value === null ? 'unlimited' : 'custom'),
                            'limitValue' => $override?->limit_value,
                            'note' => $override?->note,
                        ],
                    ];
                })
                ->values(),
            'features' => collect(PlanFeatures::keys())
                ->map(function (string $feature) use ($featureOverrides, $entitlements): array {
                    $override = $featureOverrides->get($feature);

                    return [
                        ...PlanFeatures::definition($feature),
                        'key' => $feature,
                        'enabled' => $entitlements->can($feature),
                        'source' => $entitlements->featureSource($feature),
                        'override' => [
                            'exists' => $override !== null,
                            'mode' => $override === null
                                ? 'inherit'
                                : ($override->is_enabled ? 'enabled' : 'disabled'),
                            'isEnabled' => $override?->is_enabled,
                            'note' => $override?->note,
                        ],
                    ];
                })
                ->values(),
            'statuses' => SubscriptionStatus::options(),
            'canEdit' => ChurchStatus::canEdit($church->status),
        ]);
    }

    public function updateSubscription(
        UpdateChurchSubscriptionRequest $request,
        Church $church,
    ): RedirectResponse {
        /** @var PlatformAdmin $admin */
        $admin = $request->attributes->get('platformAdmin');

        abort_unless($admin instanceof PlatformAdmin, 403);
        abort_unless(ChurchStatus::canEdit($church->status), 403);

        /** @var array{plan_id: int, status: string, notes?: ?string} $data */
        $data = $request->validated();

        DB::transaction(function () use ($church, $data, $admin): void {
            $subscription = ChurchSubscription::query()
                ->where('church_id', $church->getKey())
                ->first();

            $before = [
                'planId' => $subscription?->plan_id,
                'status' => $subscription?->status,
                'notes' => $subscription?->notes,
            ];

            $planChanged = $subscription !== null && (int) $subscription->plan_id !== (int) $data['plan_id'];

            $subscription ??= new ChurchSubscription([
                'church_id' => $church->getKey(),
                'started_at' => now(),
            ]);

            $subscription->fill([
                'plan_id' => $data['plan_id'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
                'assigned_by_platform_admin_id' => $admin->getKey(),
                'plan_changed_at' => $planChanged ? now() : $subscription->plan_changed_at,
            ])->save();

            PlatformAudit::record($admin, PlatformAudit::CHURCH_SUBSCRIPTION_UPDATED, $church, [
                'before' => $before,
                'after' => [
                    'planId' => $subscription->plan_id,
                    'status' => $subscription->status,
                    'notes' => $subscription->notes,
                ],
            ]);
        });

        return redirect()
            ->route('admin.churches.plan', $church)
            ->with('success', 'Suscripción actualizada.');
    }

    public function updateLimits(
        UpdateChurchLimitOverridesRequest $request,
        Church $church,
    ): RedirectResponse {
        /** @var PlatformAdmin $admin */
        $admin = $request->attributes->get('platformAdmin');

        abort_unless($admin instanceof PlatformAdmin, 403);
        abort_unless(ChurchStatus::canEdit($church->status), 403);

        /** @var array{limits: array<string, array{mode: string, value?: ?int}>, note?: ?string} $data */
        $data = $request->validated();

        DB::transaction(function () use ($church, $data, $admin): void {
            $before = $this->limitSnapshot($church);

            foreach (PlanResources::keys() as $resource) {
                $entry = $data['limits'][$resource] ?? ['mode' => 'inherit'];
                $override = ChurchLimitOverride::query()
                    ->where('church_id', $church->getKey())
                    ->where('resource', $resource)
                    ->first();

                if ($entry['mode'] === 'inherit') {
                    $override?->delete();

                    continue;
                }

                ChurchLimitOverride::query()->updateOrCreate(
                    [
                        'church_id' => $church->getKey(),
                        'resource' => $resource,
                    ],
                    [
                        'limit_value' => $entry['mode'] === 'unlimited' ? null : (int) ($entry['value'] ?? 0),
                        'note' => $data['note'] ?? null,
                        'created_by_platform_admin_id' => $admin->getKey(),
                    ],
                );
            }

            PlatformAudit::record($admin, PlatformAudit::CHURCH_LIMIT_OVERRIDES_UPDATED, $church, [
                'before' => $before,
                'after' => $this->limitSnapshot($church),
            ]);
        });

        return redirect()
            ->route('admin.churches.plan', $church)
            ->with('success', 'Overrides de límites actualizados.');
    }

    public function updateFeatures(
        UpdateChurchFeatureOverridesRequest $request,
        Church $church,
    ): RedirectResponse {
        /** @var PlatformAdmin $admin */
        $admin = $request->attributes->get('platformAdmin');

        abort_unless($admin instanceof PlatformAdmin, 403);
        abort_unless(ChurchStatus::canEdit($church->status), 403);

        /** @var array{features: array<string, string>, note?: ?string} $data */
        $data = $request->validated();

        DB::transaction(function () use ($church, $data, $admin): void {
            $before = $this->featureSnapshot($church);

            foreach (PlanFeatures::keys() as $feature) {
                $mode = $data['features'][$feature] ?? 'inherit';
                $override = ChurchFeatureOverride::query()
                    ->where('church_id', $church->getKey())
                    ->where('feature', $feature)
                    ->first();

                if ($mode === 'inherit') {
                    $override?->delete();

                    continue;
                }

                ChurchFeatureOverride::query()->updateOrCreate(
                    [
                        'church_id' => $church->getKey(),
                        'feature' => $feature,
                    ],
                    [
                        'is_enabled' => $mode === 'enabled',
                        'note' => $data['note'] ?? null,
                        'created_by_platform_admin_id' => $admin->getKey(),
                    ],
                );
            }

            PlatformAudit::record($admin, PlatformAudit::CHURCH_FEATURE_OVERRIDES_UPDATED, $church, [
                'before' => $before,
                'after' => $this->featureSnapshot($church),
            ]);
        });

        return redirect()
            ->route('admin.churches.plan', $church)
            ->with('success', 'Overrides de funciones actualizados.');
    }

    /**
     * @return array<string, ?int>
     */
    private function limitSnapshot(Church $church): array
    {
        return ChurchLimitOverride::query()
            ->where('church_id', $church->getKey())
            ->get()
            ->mapWithKeys(fn (ChurchLimitOverride $override): array => [
                $override->resource => $override->limit_value,
            ])
            ->all();
    }

    /**
     * @return array<string, bool>
     */
    private function featureSnapshot(Church $church): array
    {
        return ChurchFeatureOverride::query()
            ->where('church_id', $church->getKey())
            ->get()
            ->mapWithKeys(fn (ChurchFeatureOverride $override): array => [
                $override->feature => $override->is_enabled,
            ])
            ->all();
    }
}
