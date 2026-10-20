<?php

namespace App\Support\Plans;

use App\Models\ChurchSubscription;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

final class PlanMatrix
{
    public const COMMUNITY = 'comunidad';

    public const GROWTH = 'crecimiento';

    public const PRO = 'ministerio_pro';

    /**
     * Approved plan matrix. Prices are monthly and stored in the currency's minor unit.
     *
     * @return array<string, array{
     *     name: string,
     *     description: string,
     *     price_minor: int,
     *     currency_code: string,
     *     billing_interval: string,
     *     sort_order: int,
     *     is_active: bool,
     *     limits: array<string, ?int>,
     *     features: array<int, string>
     * }>
     */
    public static function plans(): array
    {
        return [
            self::COMMUNITY => [
                'name' => 'Comunidad',
                'description' => 'Operación base para iglesias que inician su organización digital.',
                'price_minor' => 0,
                'currency_code' => 'USD',
                'billing_interval' => 'month',
                'sort_order' => 10,
                'is_active' => true,
                'limits' => [
                    PlanResources::PEOPLE => 100,
                    PlanResources::ACTIVE_USERS => 5,
                    PlanResources::CURRENCIES => 1,
                    PlanResources::EVENTS_PER_MONTH => 20,
                    PlanResources::WORKFLOWS => 0,
                    PlanResources::EXECUTIONS_PER_MONTH => 0,
                    PlanResources::INTEGRATIONS => 0,
                ],
                'features' => [],
            ],
            self::GROWTH => [
                'name' => 'Crecimiento',
                'description' => 'Equipos en expansión con multi-moneda, voluntarios y seguimiento pastoral.',
                'price_minor' => 2900,
                'currency_code' => 'USD',
                'billing_interval' => 'month',
                'sort_order' => 20,
                'is_active' => true,
                'limits' => [
                    PlanResources::PEOPLE => 500,
                    PlanResources::ACTIVE_USERS => 15,
                    PlanResources::CURRENCIES => 4,
                    PlanResources::EVENTS_PER_MONTH => 100,
                    PlanResources::WORKFLOWS => 10,
                    PlanResources::EXECUTIONS_PER_MONTH => 1000,
                    PlanResources::INTEGRATIONS => 2,
                ],
                'features' => [
                    PlanFeatures::MULTI_CURRENCY,
                    PlanFeatures::VOLUNTEERS,
                    PlanFeatures::PASTORAL_PIPELINE,
                ],
            ],
            self::PRO => [
                'name' => 'Ministerio Pro',
                'description' => 'Operación avanzada con automatización, pagos y personalización de marca.',
                'price_minor' => 7900,
                'currency_code' => 'USD',
                'billing_interval' => 'month',
                'sort_order' => 30,
                'is_active' => true,
                'limits' => [
                    PlanResources::PEOPLE => 2500,
                    PlanResources::ACTIVE_USERS => 50,
                    PlanResources::CURRENCIES => 8,
                    PlanResources::EVENTS_PER_MONTH => 500,
                    PlanResources::WORKFLOWS => 50,
                    PlanResources::EXECUTIONS_PER_MONTH => 10000,
                    PlanResources::INTEGRATIONS => 10,
                ],
                'features' => PlanFeatures::keys(),
            ],
        ];
    }

    public static function defaultPlanKey(): string
    {
        return self::COMMUNITY;
    }

    public static function defaultPlan(): ?Plan
    {
        return Plan::query()->where('key', self::defaultPlanKey())->first();
    }

    /**
     * Upsert the approved matrix and assign the default plan to churches without a subscription.
     */
    public static function sync(): void
    {
        DB::transaction(function (): void {
            foreach (self::plans() as $key => $data) {
                $plan = Plan::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'name' => $data['name'],
                        'description' => $data['description'],
                        'price_minor' => $data['price_minor'],
                        'currency_code' => $data['currency_code'],
                        'billing_interval' => $data['billing_interval'],
                        'sort_order' => $data['sort_order'],
                        'is_active' => $data['is_active'],
                    ],
                );

                $plan->limits()->delete();
                $plan->features()->delete();

                foreach ($data['limits'] as $resource => $limit) {
                    $plan->limits()->create([
                        'resource' => $resource,
                        'limit_value' => $limit,
                    ]);
                }

                foreach ($data['features'] as $feature) {
                    $plan->features()->create(['feature' => $feature]);
                }
            }

            $defaultPlan = self::defaultPlan();

            if ($defaultPlan === null) {
                return;
            }

            DB::table('churches')
                ->select('id')
                ->orderBy('id')
                ->chunkById(200, function ($churches) use ($defaultPlan): void {
                    $churchIds = $churches->pluck('id')->all();
                    $subscribed = DB::table('church_subscriptions')
                        ->whereIn('church_id', $churchIds)
                        ->pluck('church_id')
                        ->all();

                    $missing = array_values(array_diff($churchIds, $subscribed));

                    if ($missing === []) {
                        return;
                    }

                    $now = now();

                    DB::table('church_subscriptions')->insert(
                        collect($missing)->map(fn (int $churchId): array => [
                            'church_id' => $churchId,
                            'plan_id' => $defaultPlan->getKey(),
                            'status' => SubscriptionStatus::ACTIVE,
                            'started_at' => $now,
                            'plan_changed_at' => null,
                            'ends_at' => null,
                            'notes' => 'Plan inicial asignado al habilitar planes y límites.',
                            'assigned_by_platform_admin_id' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->all(),
                    );
                });
        });
    }

    public static function assignDefaultTo(int $churchId): ?ChurchSubscription
    {
        $plan = self::defaultPlan();

        if ($plan === null) {
            return null;
        }

        return ChurchSubscription::query()->firstOrCreate(
            ['church_id' => $churchId],
            [
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::ACTIVE,
                'started_at' => now(),
                'notes' => 'Plan inicial asignado al crear la iglesia.',
            ],
        );
    }
}
