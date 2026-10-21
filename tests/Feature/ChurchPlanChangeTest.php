<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Support\Plans\PlanMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChurchPlanChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_change_a_church_plan(): void
    {
        $church = $this->createChurch('iglesia-plan-cambio-invitado');

        $this->put(route('churches.plan.update', $church), [
            'plan' => PlanMatrix::GROWTH,
        ])->assertRedirect(route('login'));
    }

    public function test_an_admin_can_change_the_church_plan_immediately(): void
    {
        $church = $this->createChurch('iglesia-plan-cambio-admin');
        $user = $this->createMember($church, 'admin');
        $growth = Plan::query()->where('key', PlanMatrix::GROWTH)->firstOrFail();

        $response = $this->actingAs($user)->put(route('churches.plan.update', $church), [
            'plan' => PlanMatrix::GROWTH,
        ]);

        $response
            ->assertRedirect(route('churches.plan', $church))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('church_subscriptions', [
            'church_id' => $church->getKey(),
            'plan_id' => $growth->getKey(),
            'status' => 'active',
        ]);

        $subscription = ChurchSubscription::query()
            ->where('church_id', $church->getKey())
            ->firstOrFail();

        $this->assertNotNull($subscription->plan_changed_at);

        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'plan.changed',
            'actor_user_id' => $user->getKey(),
            'subject_type' => ChurchSubscription::class,
            'subject_id' => $subscription->getKey(),
        ]);

        $this->actingAs($user)
            ->get(route('churches.plan', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('churches/plan')
                ->where('plan.plan.key', PlanMatrix::GROWTH)
                ->where('plan.plan.isFree', false)
                ->where('canChangePlan', true)
                ->has('availablePlans', 3));
    }

    public function test_finance_can_view_the_plan_but_cannot_change_it(): void
    {
        $church = $this->createChurch('iglesia-plan-cambio-finance');
        $user = $this->createMember($church, 'finance');

        $this->actingAs($user)
            ->put(route('churches.plan.update', $church), [
                'plan' => PlanMatrix::GROWTH,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('churches.plan', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('churches/plan')
                ->where('canChangePlan', false));
    }

    public function test_an_unknown_plan_is_rejected(): void
    {
        $church = $this->createChurch('iglesia-plan-cambio-invalido');
        $user = $this->createMember($church, 'admin');

        $this->actingAs($user)
            ->put(route('churches.plan.update', $church), [
                'plan' => 'plan-inexistente',
            ])
            ->assertSessionHasErrors('plan');

        $this->assertDatabaseHas('church_subscriptions', [
            'church_id' => $church->getKey(),
            'plan_id' => Plan::query()->where('key', PlanMatrix::COMMUNITY)->firstOrFail()->getKey(),
        ]);
    }

    public function test_an_admin_cannot_change_another_church_plan(): void
    {
        $ownChurch = $this->createChurch('iglesia-plan-propia');
        $otherChurch = $this->createChurch('iglesia-plan-ajena');
        $user = $this->createMember($ownChurch, 'admin');

        $this->actingAs($user)
            ->put(route('churches.plan.update', $otherChurch), [
                'plan' => PlanMatrix::GROWTH,
            ])
            ->assertNotFound();
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

    private function createMember(Church $church, string $role): User
    {
        $user = User::factory()->create();

        ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }
}
