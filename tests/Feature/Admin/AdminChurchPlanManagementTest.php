<?php

namespace Tests\Feature\Admin;

use App\Models\Church;
use App\Models\ChurchFeatureOverride;
use App\Models\ChurchLimitOverride;
use App\Models\ChurchSubscription;
use App\Models\Person;
use App\Models\Plan;
use App\Models\PlatformAdmin;
use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanFeatures;
use App\Support\Plans\PlanMatrix;
use App\Support\Plans\PlanResources;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminChurchPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_platform_admin_can_view_plan_usage_and_overrides(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-plan');

        $this->withChurchContext($church, function (): void {
            Person::create(['first_name' => 'Ana']);
        });

        $this->actingAs($admin, 'platform')
            ->get(route('admin.churches.plan', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/churches/plan')
                ->where('church.slug', 'iglesia-plan')
                ->where('subscription.planKey', PlanMatrix::COMMUNITY)
                ->has('plans', 3)
                ->has('resources', 7)
                ->has('features', 6)
                ->where('resources.0.key', PlanResources::PEOPLE)
                ->where('resources.0.used', 1)
                ->where('resources.0.limit', 100)
                ->where('canEdit', true));
    }

    public function test_the_platform_admin_can_assign_a_plan_and_change_subscription_status(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-cambio-plan');
        $growth = Plan::query()->where('key', PlanMatrix::GROWTH)->firstOrFail();

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.subscription', $church), [
                'plan_id' => $growth->getKey(),
                'status' => 'past_due',
                'notes' => 'Acuerdo comercial pendiente.',
            ])
            ->assertRedirect(route('admin.churches.plan', $church));

        $this->assertDatabaseHas('church_subscriptions', [
            'church_id' => $church->getKey(),
            'plan_id' => $growth->getKey(),
            'status' => 'past_due',
            'assigned_by_platform_admin_id' => $admin->getKey(),
        ]);
        $this->assertDatabaseHas('platform_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'church.subscription_updated',
        ]);

        $subscription = ChurchSubscription::query()->where('church_id', $church->getKey())->firstOrFail();

        $this->assertNotNull($subscription->plan_changed_at);
    }

    public function test_subscription_validation_rejects_unknown_plans_and_statuses(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-plan-invalido');

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.subscription', $church), [
                'plan_id' => 999999,
                'status' => 'unknown',
            ])
            ->assertSessionHasErrors(['plan_id', 'status']);
    }

    public function test_the_platform_admin_can_create_and_remove_limit_overrides(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-overrides-limites');

        $limits = collect(PlanResources::keys())->mapWithKeys(fn (string $resource): array => [
            $resource => ['mode' => 'inherit'],
        ])->all();

        $limits[PlanResources::PEOPLE] = ['mode' => 'custom', 'value' => 250];
        $limits[PlanResources::INTEGRATIONS] = ['mode' => 'unlimited'];

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.limits', $church), [
                'limits' => $limits,
                'note' => 'Ajuste temporal para el piloto.',
            ])
            ->assertRedirect(route('admin.churches.plan', $church));

        $this->assertDatabaseHas('church_limit_overrides', [
            'church_id' => $church->getKey(),
            'resource' => PlanResources::PEOPLE,
            'limit_value' => 250,
        ]);
        $this->assertDatabaseHas('church_limit_overrides', [
            'church_id' => $church->getKey(),
            'resource' => PlanResources::INTEGRATIONS,
            'limit_value' => null,
        ]);
        $this->assertDatabaseHas('platform_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'church.limit_overrides_updated',
        ]);

        $entitlements = Entitlements::forChurch($church);
        $this->assertSame(250, $entitlements->limit(PlanResources::PEOPLE));
        $this->assertNull($entitlements->limit(PlanResources::INTEGRATIONS));

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.limits', $church), [
                'limits' => collect(PlanResources::keys())->mapWithKeys(fn (string $resource): array => [
                    $resource => ['mode' => 'inherit'],
                ])->all(),
            ])
            ->assertRedirect(route('admin.churches.plan', $church));

        $this->assertSame(0, ChurchLimitOverride::query()->where('church_id', $church->getKey())->count());
    }

    public function test_limit_validation_rejects_unknown_resources_and_missing_custom_values(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-limites-invalidos');

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.limits', $church), [
                'limits' => [
                    'unknown_resource' => ['mode' => 'custom', 'value' => 3],
                    PlanResources::PEOPLE => ['mode' => 'custom'],
                ],
            ])
            ->assertSessionHasErrors([
                'limits.unknown_resource',
                'limits.people.value',
            ]);
    }

    public function test_the_platform_admin_can_create_and_remove_feature_overrides(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-overrides-features');

        $features = collect(PlanFeatures::keys())->mapWithKeys(fn (string $feature): array => [
            $feature => 'inherit',
        ])->all();

        $features[PlanFeatures::AUTOMATION] = 'enabled';
        $features[PlanFeatures::PAYMENTS] = 'disabled';

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.features', $church), [
                'features' => $features,
            ])
            ->assertRedirect(route('admin.churches.plan', $church));

        $this->assertDatabaseHas('church_feature_overrides', [
            'church_id' => $church->getKey(),
            'feature' => PlanFeatures::AUTOMATION,
            'is_enabled' => true,
        ]);
        $this->assertDatabaseHas('church_feature_overrides', [
            'church_id' => $church->getKey(),
            'feature' => PlanFeatures::PAYMENTS,
            'is_enabled' => false,
        ]);
        $this->assertDatabaseHas('platform_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'church.feature_overrides_updated',
        ]);

        $entitlements = Entitlements::forChurch($church);
        $this->assertTrue($entitlements->can(PlanFeatures::AUTOMATION));
        $this->assertFalse($entitlements->can(PlanFeatures::PAYMENTS));

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.features', $church), [
                'features' => collect(PlanFeatures::keys())->mapWithKeys(fn (string $feature): array => [
                    $feature => 'inherit',
                ])->all(),
            ]);

        $this->assertSame(0, ChurchFeatureOverride::query()->where('church_id', $church->getKey())->count());
    }

    public function test_feature_validation_rejects_unknown_features_and_modes(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-features-invalidas');

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.features', $church), [
                'features' => [
                    'unknown_feature' => 'enabled',
                    PlanFeatures::AUTOMATION => 'maybe',
                ],
            ])
            ->assertSessionHasErrors([
                'features.unknown_feature',
                'features.automation',
            ]);
    }

    public function test_an_archived_church_cannot_change_plan_or_overrides(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-plan-archivada', 'archived');
        $growth = Plan::query()->where('key', PlanMatrix::GROWTH)->firstOrFail();

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.subscription', $church), [
                'plan_id' => $growth->getKey(),
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.plan.limits', $church), [
                'limits' => [
                    PlanResources::PEOPLE => ['mode' => 'unlimited'],
                ],
            ])
            ->assertForbidden();
    }

    private function createChurch(string $slug, string $status = 'active'): Church
    {
        return Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => $status,
            'country_code' => 'VE',
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
            'locale' => 'es',
            'archived_at' => $status === 'archived' ? now() : null,
        ]);
    }

    private function withChurchContext(Church $church, callable $callback): mixed
    {
        $currentChurch = app(CurrentChurch::class);
        $currentChurch->set($church);

        try {
            return $callback();
        } finally {
            $currentChurch->clear();
        }
    }
}
