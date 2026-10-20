<?php

namespace Tests\Feature\Plans;

use App\Exceptions\PlanLimitExceededException;
use App\Http\Middleware\EnsurePlanFeature;
use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchFeatureOverride;
use App\Models\ChurchLimitOverride;
use App\Models\ChurchMembership;
use App\Models\ChurchSubscription;
use App\Models\Event;
use App\Models\Person;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanEnforcement;
use App\Support\Plans\PlanFeatures;
use App\Support\Plans\PlanMatrix;
use App\Support\Plans\PlanResources;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlanEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_church_receives_the_community_plan(): void
    {
        $church = $this->createChurch('iglesia-nueva-plan');

        $this->assertDatabaseHas('church_subscriptions', [
            'church_id' => $church->getKey(),
            'status' => 'active',
        ]);

        $entitlements = Entitlements::forChurch($church);

        $this->assertSame(PlanMatrix::COMMUNITY, $entitlements->plan()?->key);
        $this->assertSame(100, $entitlements->limit(PlanResources::PEOPLE));
        $this->assertSame(0, $entitlements->limit(PlanResources::WORKFLOWS));
        $this->assertFalse($entitlements->can(PlanFeatures::AUTOMATION));
    }

    public function test_usage_counts_each_resource_with_the_expected_scope(): void
    {
        $church = $this->createChurch('iglesia-uso');

        $this->withChurchContext($church, function (): void {
            Person::create(['first_name' => 'Activa', 'status' => 'active']);
            Person::create(['first_name' => 'Archivada', 'status' => 'archived']);
            Event::create(['title' => 'Culto', 'status' => 'published']);
            Event::create(['title' => 'Ensayo', 'status' => 'draft']);
        });

        $activeUser = User::factory()->create();
        $suspendedUser = User::factory()->create();

        ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => $activeUser->getKey(),
            'role' => 'admin',
            'status' => 'active',
        ]);
        ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => $suspendedUser->getKey(),
            'role' => 'leader',
            'status' => 'suspended',
        ]);

        $this->withChurchContext($church, function (): void {
            ChurchCurrency::create(['currency_code' => 'USD', 'is_default' => true]);
        });

        [$workflow] = $this->withChurchContext($church, fn (): array => PlanEnforcement::without(function (): array {
            $workflow = Workflow::create([
                'name' => 'Bienvenida',
                'trigger_type' => 'manual',
                'is_active' => true,
            ]);

            $version = WorkflowVersion::create([
                'workflow_id' => $workflow->getKey(),
                'version' => 1,
                'definition' => ['nodes' => []],
            ]);

            WorkflowRun::create([
                'workflow_id' => $workflow->getKey(),
                'workflow_version_id' => $version->getKey(),
                'status' => 'completed',
            ]);

            return [$workflow];
        }));

        $usage = Entitlements::forChurch($church)->usageSummary();

        $this->assertSame(1, $usage[PlanResources::PEOPLE]['used']);
        $this->assertSame(1, $usage[PlanResources::ACTIVE_USERS]['used']);
        $this->assertSame(1, $usage[PlanResources::CURRENCIES]['used']);
        $this->assertSame(2, $usage[PlanResources::EVENTS_PER_MONTH]['used']);
        $this->assertSame(1, $usage[PlanResources::WORKFLOWS]['used']);
        $this->assertSame(1, $usage[PlanResources::EXECUTIONS_PER_MONTH]['used']);
        $this->assertSame(0, $usage[PlanResources::INTEGRATIONS]['used']);
        $this->assertNotNull($workflow->getKey());
    }

    public function test_plan_limits_block_new_records_but_allow_editing_existing_ones(): void
    {
        $church = $this->createChurch('iglesia-limite-personas');

        ChurchLimitOverride::create([
            'church_id' => $church->getKey(),
            'resource' => PlanResources::PEOPLE,
            'limit_value' => 1,
        ]);

        $person = $this->withChurchContext($church, fn (): Person => Person::create([
            'first_name' => 'Primera',
        ]));

        try {
            $this->withChurchContext($church, fn (): Person => Person::create([
                'first_name' => 'Segunda',
            ]));

            $this->fail('Creating a person over the limit should be rejected.');
        } catch (PlanLimitExceededException $exception) {
            $this->assertSame(PlanResources::PEOPLE, $exception->resource);
            $this->assertSame(1, $exception->limit);
            $this->assertSame(1, $exception->used);
        }

        $this->withChurchContext($church, function () use ($person): void {
            $person->update(['first_name' => 'Primera editada']);
        });

        $this->assertSame('Primera editada', $person->refresh()->first_name);
    }

    public function test_active_user_limit_blocks_new_memberships(): void
    {
        $church = $this->createChurch('iglesia-limite-usuarios');

        ChurchLimitOverride::create([
            'church_id' => $church->getKey(),
            'resource' => PlanResources::ACTIVE_USERS,
            'limit_value' => 1,
        ]);

        ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => User::factory()->create()->getKey(),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->expectException(PlanLimitExceededException::class);

        ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => User::factory()->create()->getKey(),
            'role' => 'leader',
            'status' => 'active',
        ]);
    }

    public function test_execution_limit_blocks_new_workflow_runs(): void
    {
        $church = $this->createChurch('iglesia-limite-ejecuciones');

        [$workflow, $version] = $this->withChurchContext($church, fn (): array => PlanEnforcement::without(function (): array {
            $workflow = Workflow::create([
                'name' => 'Workflow con ejecuciones',
                'trigger_type' => 'manual',
            ]);

            $version = WorkflowVersion::create([
                'workflow_id' => $workflow->getKey(),
                'version' => 1,
                'definition' => ['nodes' => []],
            ]);

            return [$workflow, $version];
        }));

        $this->assertSame(0, Entitlements::forChurch($church)->limit(PlanResources::EXECUTIONS_PER_MONTH));

        $this->expectException(PlanLimitExceededException::class);

        $this->withChurchContext($church, fn (): WorkflowRun => WorkflowRun::create([
            'workflow_id' => $workflow->getKey(),
            'workflow_version_id' => $version->getKey(),
            'status' => 'pending',
        ]));
    }

    public function test_an_override_can_raise_a_limit_above_the_plan(): void
    {
        $church = $this->createChurch('iglesia-override-sube');

        ChurchLimitOverride::create([
            'church_id' => $church->getKey(),
            'resource' => PlanResources::PEOPLE,
            'limit_value' => 2,
        ]);

        $this->withChurchContext($church, function (): void {
            Person::create(['first_name' => 'Uno']);
            Person::create(['first_name' => 'Dos']);
        });

        $entitlements = Entitlements::forChurch($church);

        $this->assertSame(2, $entitlements->limit(PlanResources::PEOPLE));
        $this->assertSame('override', $entitlements->limitSource(PlanResources::PEOPLE));

        $this->expectException(PlanLimitExceededException::class);

        $this->withChurchContext($church, fn (): Person => Person::create([
            'first_name' => 'Tres',
        ]));
    }

    public function test_a_null_override_means_unlimited(): void
    {
        $church = $this->createChurch('iglesia-override-ilimitado');

        ChurchLimitOverride::create([
            'church_id' => $church->getKey(),
            'resource' => PlanResources::WORKFLOWS,
            'limit_value' => null,
        ]);

        $entitlements = Entitlements::forChurch($church);

        $this->assertNull($entitlements->limit(PlanResources::WORKFLOWS));
        $this->assertTrue($entitlements->isUnlimited(PlanResources::WORKFLOWS));

        $this->withChurchContext($church, function (): void {
            Workflow::create([
                'name' => 'Sin límite',
                'trigger_type' => 'manual',
            ]);
        });

        $this->assertSame(1, Entitlements::forChurch($church)->usageFor(PlanResources::WORKFLOWS));
    }

    public function test_a_downgrade_keeps_data_and_blocks_new_records_and_features(): void
    {
        $church = $this->createChurch('iglesia-downgrade');
        $growth = Plan::query()->where('key', PlanMatrix::GROWTH)->firstOrFail();

        ChurchSubscription::query()
            ->where('church_id', $church->getKey())
            ->update(['plan_id' => $growth->getKey()]);

        $workflow = $this->withChurchContext($church, fn (): Workflow => Workflow::create([
            'name' => 'Automatización existente',
            'trigger_type' => 'manual',
        ]));

        $this->assertTrue(Entitlements::forChurch($church)->can(PlanFeatures::PASTORAL_PIPELINE));

        $community = Plan::query()->where('key', PlanMatrix::COMMUNITY)->firstOrFail();

        ChurchSubscription::query()
            ->where('church_id', $church->getKey())
            ->update(['plan_id' => $community->getKey()]);

        $entitlements = Entitlements::forChurch($church);

        $this->assertFalse($entitlements->can(PlanFeatures::PASTORAL_PIPELINE));
        $this->assertSame(1, $entitlements->usageFor(PlanResources::WORKFLOWS));
        $this->assertDatabaseHas('workflows', ['id' => $workflow->getKey()]);

        $this->expectException(PlanLimitExceededException::class);

        $this->withChurchContext($church, fn (): Workflow => Workflow::create([
            'name' => 'Nuevo workflow bloqueado',
            'trigger_type' => 'manual',
        ]));
    }

    public function test_feature_overrides_take_precedence_over_the_plan(): void
    {
        $church = $this->createChurch('iglesia-override-feature');

        ChurchFeatureOverride::create([
            'church_id' => $church->getKey(),
            'feature' => PlanFeatures::AUTOMATION,
            'is_enabled' => true,
        ]);

        $entitlements = Entitlements::forChurch($church);

        $this->assertTrue($entitlements->can(PlanFeatures::AUTOMATION));
        $this->assertSame('override', $entitlements->featureSource(PlanFeatures::AUTOMATION));

        ChurchFeatureOverride::query()
            ->where('church_id', $church->getKey())
            ->where('feature', PlanFeatures::AUTOMATION)
            ->update(['is_enabled' => false]);

        $this->assertFalse(Entitlements::forChurch($church)->can(PlanFeatures::AUTOMATION));
    }

    public function test_the_feature_gate_blocks_disabled_features(): void
    {
        $church = $this->createChurch('iglesia-gate-feature');
        app(CurrentChurch::class)->set($church);

        $middleware = app(EnsurePlanFeature::class);
        $request = Request::create('/feature-test', 'GET');

        try {
            $middleware->handle($request, fn () => response('ok'), PlanFeatures::AUTOMATION);
            $this->fail('A disabled feature should be blocked.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        ChurchFeatureOverride::create([
            'church_id' => $church->getKey(),
            'feature' => PlanFeatures::AUTOMATION,
            'is_enabled' => true,
        ]);

        $response = $middleware->handle($request, fn () => response('ok'), PlanFeatures::AUTOMATION);

        $this->assertSame(200, $response->getStatusCode());

        app(CurrentChurch::class)->clear();
    }

    public function test_the_matrix_can_be_synced_idempotently(): void
    {
        $church = $this->createChurch('iglesia-sync');

        PlanMatrix::sync();
        PlanMatrix::sync();

        $this->assertSame(3, Plan::query()->count());
        $this->assertSame(1, ChurchSubscription::query()->where('church_id', $church->getKey())->count());
        $this->assertSame(
            7,
            Plan::query()->where('key', PlanMatrix::COMMUNITY)->firstOrFail()->limits()->count(),
        );

        ChurchSubscription::query()->where('church_id', $church->getKey())->delete();
        PlanMatrix::sync();

        $this->assertDatabaseHas('church_subscriptions', [
            'church_id' => $church->getKey(),
            'status' => 'active',
        ]);
    }

    private function createChurch(string $slug): Church
    {
        return Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'active',
            'country_code' => 'VE',
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
            'locale' => 'es',
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
