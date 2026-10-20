<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\Plans\PlanMatrix;
use App\Support\Plans\PlanResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChurchPlanPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_church_plan(): void
    {
        $church = $this->createChurch('iglesia-plan-invitado');

        $this->get(route('churches.plan', $church))->assertRedirect(route('login'));
    }

    public function test_admins_and_finance_can_view_the_church_plan(): void
    {
        foreach (['admin', 'finance'] as $role) {
            $church = $this->createChurch("iglesia-plan-{$role}");
            $user = $this->createMember($church, $role);

            $this->actingAs($user)
                ->get(route('churches.plan', $church))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('churches/plan')
                    ->where('church.slug', $church->slug)
                    ->where('membership.role', $role)
                    ->where('plan.plan.key', PlanMatrix::COMMUNITY)
                    ->where('plan.subscription.status', 'active')
                    ->has('plan.usage', 7)
                    ->where('plan.usage.'.PlanResources::PEOPLE.'.limit', 100)
                    ->has('plan.features', 6));
        }
    }

    public function test_other_roles_cannot_view_the_church_plan(): void
    {
        foreach (['pastor', 'leader', 'volunteer'] as $role) {
            $church = $this->createChurch("iglesia-plan-{$role}");
            $user = $this->createMember($church, $role);

            $this->actingAs($user)
                ->get(route('churches.plan', $church))
                ->assertForbidden();
        }
    }

    public function test_a_non_member_cannot_view_another_church_plan(): void
    {
        $church = $this->createChurch('iglesia-plan-privada');
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('churches.plan', $church))
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
