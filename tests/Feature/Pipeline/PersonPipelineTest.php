<?php

namespace Tests\Feature\Pipeline;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchSubscription;
use App\Models\Person;
use App\Models\PersonPipelineProgress;
use App\Models\PipelineStage;
use App\Models\Plan;
use App\Models\User;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_requires_the_pastoral_pipeline_feature(): void
    {
        $church = $this->createChurch('pipeline-comunidad');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->get(route('pipeline.index', $church))
            ->assertForbidden();
    }

    public function test_an_admin_can_manage_pipeline_stages_on_a_growth_plan(): void
    {
        $church = $this->createChurch('pipeline-crecimiento');
        $this->assignPlan($church, 'crecimiento');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->get(route('pipeline.index', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/pipeline')
                ->has('stages', 0)
                ->where('canManage', true));

        $this->actingAs($admin)
            ->post(route('pipeline.store', $church), [
                'name' => 'Bautizado',
                'position' => 1,
                'is_active' => true,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $stage = PipelineStage::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame('Bautizado', $stage->name);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'pipeline.stage_created',
            'subject_id' => $stage->getKey(),
        ]);

        $this->actingAs($admin)
            ->put(route('pipeline.update', [
                'church' => $church,
                'stage' => $stage->getKey(),
            ]), [
                'name' => 'Bautizado en agua',
                'position' => 2,
                'is_active' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $stage->refresh();
        $this->assertSame('Bautizado en agua', $stage->name);
        $this->assertSame(2, $stage->position);
        $this->assertFalse($stage->is_active);
    }

    public function test_a_stage_can_be_completed_and_reopened_for_a_person(): void
    {
        $church = $this->createChurch('pipeline-progreso');
        $this->assignPlan($church, 'crecimiento');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Ana', 'Pérez');
        $stage = $this->createStage($church, 'Nuevo');

        $this->actingAs($admin)
            ->post(route('people.pipeline.complete', [
                'church' => $church,
                'person' => $person->getKey(),
                'stage' => $stage->getKey(),
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $progress = PersonPipelineProgress::withoutGlobalScope('church')->firstOrFail();
        $this->assertSame('completed', $progress->status);
        $this->assertNotNull($progress->completed_at);
        $this->assertSame($admin->getKey(), $progress->completed_by);

        $this->actingAs($admin)
            ->post(route('people.pipeline.complete', [
                'church' => $church,
                'person' => $person->getKey(),
                'stage' => $stage->getKey(),
            ]))
            ->assertRedirect();

        $this->assertSame(1, PersonPipelineProgress::withoutGlobalScope('church')->count());

        $this->actingAs($admin)
            ->get(route('people.show', ['church' => $church, 'person' => $person->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/show')
                ->where('canManagePipeline', true)
                ->has('pipeline', 1)
                ->where('pipeline.0.name', 'Nuevo')
                ->whereNot('pipeline.0.completedAt', null));

        $this->actingAs($admin)
            ->delete(route('people.pipeline.reopen', [
                'church' => $church,
                'person' => $person->getKey(),
                'stage' => $stage->getKey(),
            ]))
            ->assertRedirect();

        $progress->refresh();
        $this->assertSame('in_progress', $progress->status);
        $this->assertNull($progress->completed_at);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'pipeline.stage_reopened',
        ]);
    }

    public function test_pipeline_stages_are_isolated_between_churches(): void
    {
        $alpha = $this->createChurch('pipeline-alpha');
        $this->assignPlan($alpha, 'crecimiento');
        $alphaStage = $this->createStage($alpha, 'Alpha');

        $beta = $this->createChurch('pipeline-beta');
        $this->assignPlan($beta, 'crecimiento');
        $betaAdmin = $this->createMember($beta, 'admin');
        $betaPerson = $this->createPerson($beta, 'Beto', 'Rojas');

        $this->actingAs($betaAdmin)
            ->post(route('people.pipeline.complete', [
                'church' => $beta,
                'person' => $betaPerson->getKey(),
                'stage' => $alphaStage->getKey(),
            ]))
            ->assertNotFound();
    }

    private function createStage(Church $church, string $name): PipelineStage
    {
        return app(CurrentChurch::class)->run($church, fn (): PipelineStage => PipelineStage::create([
            'name' => $name,
            'position' => 0,
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
