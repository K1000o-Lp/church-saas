<?php

namespace Tests\Feature\Services;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchSubscription;
use App\Models\MinistryTeam;
use App\Models\Person;
use App\Models\Plan;
use App\Models\ServiceItem;
use App\Models\ServicePlan;
use App\Models\User;
use App\Models\VolunteerAssignment;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ServicePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_require_the_volunteers_feature(): void
    {
        $church = $this->createChurch('servicios-comunidad');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->get(route('services.index', $church))
            ->assertForbidden();
    }

    public function test_an_admin_can_manage_teams_and_service_plans(): void
    {
        $church = $this->createChurch('servicios-crud');
        $this->assignPlan($church, 'crecimiento');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->get(route('services.index', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('services/index')
                ->has('plans', 0)
                ->where('canManage', true));

        $this->actingAs($admin)
            ->post(route('services.teams.store', $church), [
                'name' => 'Alabanza',
                'description' => 'Equipo de música.',
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ministry_teams', [
            'church_id' => $church->getKey(),
            'name' => 'Alabanza',
        ]);

        $this->actingAs($admin)
            ->post(route('services.store', $church), [
                'title' => 'Culto dominical',
                'starts_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(2)->addHours(2)->format('Y-m-d H:i:s'),
                'location' => 'Templo',
                'status' => 'draft',
            ])
            ->assertRedirect();

        $plan = ServicePlan::withoutGlobalScope('church')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('services.publish', [
                'church' => $church,
                'service' => $plan->getKey(),
            ]))
            ->assertRedirect();

        $this->assertSame('published', $plan->refresh()->status);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'service.published',
        ]);
    }

    public function test_items_and_volunteer_assignments_can_be_managed(): void
    {
        $church = $this->createChurch('servicios-items');
        $this->assignPlan($church, 'crecimiento');
        $admin = $this->createMember($church, 'admin');
        $plan = $this->createPlan($church);
        $person = $this->createPerson($church, 'Ana', 'Pérez');
        $team = $this->createTeam($church, 'Alabanza');

        $this->actingAs($admin)
            ->post(route('services.items.store', [
                'church' => $church,
                'service' => $plan->getKey(),
            ]), [
                'title' => 'Adoración',
                'position' => 1,
                'ministry_team_id' => $team->getKey(),
                'notes' => 'Bloque de 20 minutos.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $item = ServiceItem::withoutGlobalScope('church')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('services.assignments.store', [
                'church' => $church,
                'service' => $plan->getKey(),
                'item' => $item->getKey(),
            ]), [
                'person_id' => $person->getKey(),
                'role' => 'Voz principal',
                'status' => 'invited',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $assignment = VolunteerAssignment::withoutGlobalScope('church')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('services.assignments.update', [
                'church' => $church,
                'service' => $plan->getKey(),
                'item' => $item->getKey(),
                'assignment' => $assignment->getKey(),
            ]), ['status' => 'confirmed'])
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame('confirmed', $assignment->status);
        $this->assertNotNull($assignment->confirmed_at);

        $this->actingAs($admin)
            ->get(route('services.show', [
                'church' => $church,
                'service' => $plan->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('services/show')
                ->has('items', 1)
                ->where('items.0.assignments.0.status', 'confirmed')
                ->where('canManageVolunteers', true));

        $this->actingAs($admin)
            ->delete(route('services.assignments.destroy', [
                'church' => $church,
                'service' => $plan->getKey(),
                'item' => $item->getKey(),
                'assignment' => $assignment->getKey(),
            ]))
            ->assertRedirect();

        $this->assertSame(0, VolunteerAssignment::withoutGlobalScope('church')->count());
    }

    public function test_duplicate_volunteer_assignments_are_rejected(): void
    {
        $church = $this->createChurch('servicios-duplicado');
        $this->assignPlan($church, 'crecimiento');
        $admin = $this->createMember($church, 'admin');
        $plan = $this->createPlan($church);
        $person = $this->createPerson($church, 'Beto', 'Rojas');
        $item = $this->createItem($plan, 'Predicación');

        foreach ([1, 2] as $attempt) {
            $this->actingAs($admin)
                ->post(route('services.assignments.store', [
                    'church' => $church,
                    'service' => $plan->getKey(),
                    'item' => $item->getKey(),
                ]), [
                    'person_id' => $person->getKey(),
                    'role' => 'Predicador',
                    'status' => 'invited',
                ])
                ->assertRedirect();
        }

        $this->assertSame(1, VolunteerAssignment::withoutGlobalScope('church')->count());
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'volunteer.assigned',
        ]);
    }

    public function test_service_plans_are_isolated_between_churches(): void
    {
        $alpha = $this->createChurch('servicios-alpha');
        $this->assignPlan($alpha, 'crecimiento');
        $alphaPlan = $this->createPlan($alpha);

        $beta = $this->createChurch('servicios-beta');
        $this->assignPlan($beta, 'crecimiento');
        $betaAdmin = $this->createMember($beta, 'admin');

        $this->actingAs($betaAdmin)
            ->get(route('services.show', [
                'church' => $beta,
                'service' => $alphaPlan->getKey(),
            ]))
            ->assertNotFound();
    }

    private function createPlan(Church $church): ServicePlan
    {
        return app(CurrentChurch::class)->run($church, fn (): ServicePlan => ServicePlan::create([
            'title' => 'Culto de prueba',
            'starts_at' => now()->addDays(2),
            'location' => 'Templo',
            'status' => 'draft',
        ]));
    }

    private function createItem(ServicePlan $plan, string $title): ServiceItem
    {
        return app(CurrentChurch::class)->run($plan->church_id, fn (): ServiceItem => ServiceItem::create([
            'service_plan_id' => $plan->getKey(),
            'title' => $title,
            'position' => 1,
        ]));
    }

    private function createTeam(Church $church, string $name): MinistryTeam
    {
        return app(CurrentChurch::class)->run($church, fn (): MinistryTeam => MinistryTeam::create([
            'name' => $name,
            'is_active' => true,
        ]));
    }

    private function createPerson(Church $church, string $firstName, string $lastName): Person
    {
        return app(CurrentChurch::class)->run($church, fn (): Person => Person::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => 'active',
            'custom_fields' => (object) [],
        ]));
    }

    private function assignPlan(Church $church, string $key): void
    {
        $plan = Plan::query()->where('key', $key)->firstOrFail();

        ChurchSubscription::query()
            ->where('church_id', $church->getKey())
            ->update(['plan_id' => $plan->getKey()]);
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
