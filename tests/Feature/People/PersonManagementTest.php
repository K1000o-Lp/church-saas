<?php

namespace Tests\Feature\People;

use App\Models\Church;
use App\Models\ChurchLimitOverride;
use App\Models\ChurchMembership;
use App\Models\CustomFieldDefinition;
use App\Models\Person;
use App\Models\User;
use App\Support\Plans\PlanResources;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PersonManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_people_index_and_create_screens_render_with_definitions(): void
    {
        $church = $this->createChurch('iglesia-personas-ui');
        $admin = $this->createMember($church, 'admin');

        $this->withChurchContext($church, function (): void {
            CustomFieldDefinition::create([
                'key' => 'birth_date',
                'label' => 'Fecha de nacimiento',
                'type' => 'date',
                'is_required' => false,
                'is_active' => true,
            ]);
        });

        $this->actingAs($admin)
            ->get(route('people.index', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/index')
                ->where('church.slug', $church->slug)
                ->has('people', 0)
                ->where('canCreate', true));

        $this->actingAs($admin)
            ->get(route('people.create', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('people/create')
                ->has('definitions', 1)
                ->where('definitions.0.key', 'birth_date'));
    }

    public function test_an_admin_can_create_a_person_with_custom_fields(): void
    {
        $church = $this->createChurch('iglesia-personas-crear');
        $admin = $this->createMember($church, 'admin');

        $this->withChurchContext($church, function (): void {
            CustomFieldDefinition::create([
                'key' => 'birth_date',
                'label' => 'Fecha de nacimiento',
                'type' => 'date',
                'is_required' => true,
                'is_active' => true,
            ]);
            CustomFieldDefinition::create([
                'key' => 'marital_status',
                'label' => 'Estado civil',
                'type' => 'select',
                'options' => [
                    ['value' => 'single', 'label' => 'Soltero'],
                    ['value' => 'married', 'label' => 'Casado'],
                ],
                'is_required' => false,
                'is_active' => true,
            ]);
            CustomFieldDefinition::create([
                'key' => 'is_member',
                'label' => 'Es miembro',
                'type' => 'boolean',
                'is_required' => false,
                'is_active' => true,
            ]);
        });

        $response = $this->actingAs($admin)->post(route('people.store', $church), [
            'first_name' => '  Ana  ',
            'last_name' => '',
            'email' => 'ANA@EXAMPLE.COM',
            'phone' => ' +58 412 000 0000 ',
            'custom_fields' => [
                'birth_date' => '1990-05-10',
                'marital_status' => 'married',
                'is_member' => true,
                'unknown_field' => 'ignored',
            ],
        ]);

        $person = Person::withoutGlobalScope('church')->firstOrFail();

        $response->assertRedirect(route('people.show', ['church' => $church, 'person' => $person]));

        $this->assertSame('Ana', $person->first_name);
        $this->assertNull($person->last_name);
        $this->assertSame('ana@example.com', $person->email);
        $this->assertSame('+58 412 000 0000', $person->phone);
        $this->assertSame('1990-05-10', $person->custom_fields['birth_date']);
        $this->assertSame('married', $person->custom_fields['marital_status']);
        $this->assertTrue($person->custom_fields['is_member']);
        $this->assertArrayNotHasKey('unknown_field', $person->custom_fields);

        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'person.created',
            'subject_id' => $person->getKey(),
        ]);
    }

    public function test_a_person_can_be_created_without_custom_fields(): void
    {
        $church = $this->createChurch('iglesia-personas-sin-campos');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->post(route('people.store', $church), ['first_name' => 'Ana'])
            ->assertSessionHasNoErrors();

        $person = Person::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame([], $person->custom_fields);
        $this->assertDatabaseHas('people', ['id' => $person->getKey()]);
    }

    public function test_required_and_optioned_custom_fields_are_validated(): void
    {
        $church = $this->createChurch('iglesia-personas-validacion');
        $admin = $this->createMember($church, 'admin');

        $this->withChurchContext($church, function (): void {
            CustomFieldDefinition::create([
                'key' => 'birth_date',
                'label' => 'Fecha de nacimiento',
                'type' => 'date',
                'is_required' => true,
                'is_active' => true,
            ]);
            CustomFieldDefinition::create([
                'key' => 'marital_status',
                'label' => 'Estado civil',
                'type' => 'select',
                'options' => [['value' => 'single', 'label' => 'Soltero']],
                'is_required' => false,
                'is_active' => true,
            ]);
        });

        $this->actingAs($admin)
            ->post(route('people.store', $church), [
                'first_name' => 'Ana',
                'custom_fields' => [
                    'marital_status' => 'unknown',
                ],
            ])
            ->assertSessionHasErrors([
                'custom_fields.birth_date',
                'custom_fields.marital_status',
            ]);

        $this->assertSame(0, Person::withoutGlobalScope('church')->count());
    }

    public function test_updates_preserve_values_from_deactivated_fields_and_clear_optional_values(): void
    {
        $church = $this->createChurch('iglesia-personas-actualizar');
        $admin = $this->createMember($church, 'admin');

        $person = $this->withChurchContext($church, function (): Person {
            CustomFieldDefinition::create([
                'key' => 'nickname',
                'label' => 'Apodo',
                'type' => 'text',
                'is_required' => false,
                'is_active' => true,
            ]);

            return Person::create([
                'first_name' => 'Ana',
                'custom_fields' => ['nickname' => 'Anita', 'legacy' => 'kept'],
            ]);
        });

        $this->withChurchContext($church, function (): void {
            CustomFieldDefinition::query()->where('key', 'nickname')->update(['is_active' => false]);
        });

        $this->actingAs($admin)
            ->put(route('people.update', ['church' => $church, 'person' => $person]), [
                'first_name' => 'Ana María',
                'custom_fields' => [],
            ])
            ->assertSessionHasNoErrors();

        $person->refresh();

        $this->assertSame('Ana María', $person->first_name);
        $this->assertSame('Anita', $person->custom_fields['nickname']);
        $this->assertSame('kept', $person->custom_fields['legacy']);
    }

    public function test_people_are_archived_restored_and_audited(): void
    {
        $church = $this->createChurch('iglesia-personas-archivo');
        $admin = $this->createMember($church, 'admin');
        $person = $this->withChurchContext($church, fn (): Person => Person::create(['first_name' => 'Ana']));

        $this->actingAs($admin)
            ->patch(route('people.archive', ['church' => $church, 'person' => $person]))
            ->assertRedirect();

        $this->assertSame('archived', $person->refresh()->status);

        $this->actingAs($admin)
            ->patch(route('people.restore', ['church' => $church, 'person' => $person]))
            ->assertRedirect();

        $this->assertSame('active', $person->refresh()->status);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'person.archived',
            'subject_id' => $person->getKey(),
        ]);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'person.restored',
            'subject_id' => $person->getKey(),
        ]);
    }

    public function test_the_people_limit_is_enforced_on_creation(): void
    {
        $church = $this->createChurch('iglesia-personas-limite');
        $admin = $this->createMember($church, 'admin');

        ChurchLimitOverride::create([
            'church_id' => $church->getKey(),
            'resource' => PlanResources::PEOPLE,
            'limit_value' => 1,
        ]);

        $this->withChurchContext($church, fn () => Person::create(['first_name' => 'Primera']));

        $this->actingAs($admin)
            ->post(route('people.store', $church), ['first_name' => 'Segunda'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(1, Person::withoutGlobalScope('church')->count());
    }

    public function test_a_person_from_another_church_is_not_accessible(): void
    {
        $churchA = $this->createChurch('iglesia-personas-a');
        $churchB = $this->createChurch('iglesia-personas-b');
        $admin = $this->createMember($churchA, 'admin');
        $personB = $this->withChurchContext($churchB, fn (): Person => Person::create(['first_name' => 'Otra']));

        $this->actingAs($admin)
            ->get(route('people.show', ['church' => $churchA, 'person' => $personB]))
            ->assertNotFound();

        $this->actingAs($admin)
            ->put(route('people.update', ['church' => $churchA, 'person' => $personB]), [
                'first_name' => 'Intruso',
            ])
            ->assertNotFound();
    }

    public function test_a_leader_can_view_but_not_create_or_edit_people(): void
    {
        $church = $this->createChurch('iglesia-personas-lider');
        $leader = $this->createMember($church, 'leader');
        $person = $this->withChurchContext($church, fn (): Person => Person::create(['first_name' => 'Ana']));

        $this->actingAs($leader)
            ->get(route('people.show', ['church' => $church, 'person' => $person]))
            ->assertOk();

        $this->actingAs($leader)
            ->get(route('people.create', $church))
            ->assertForbidden();

        $this->actingAs($leader)
            ->put(route('people.update', ['church' => $church, 'person' => $person]), [
                'first_name' => 'Editada',
            ])
            ->assertForbidden();
    }

    public function test_custom_field_definitions_can_be_created_toggled_and_validated(): void
    {
        $church = $this->createChurch('iglesia-campos-personalizados');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->post(route('custom-fields.store', $church), [
                'key' => 'marital_status',
                'label' => 'Estado civil',
                'type' => 'select',
                'options' => ['single', 'married'],
                'is_required' => false,
                'is_active' => true,
            ])
            ->assertSessionHasNoErrors();

        $definition = CustomFieldDefinition::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame(['single', 'married'], array_column($definition->options, 'value'));

        $this->actingAs($admin)
            ->patch(route('custom-fields.toggle', ['church' => $church, 'definition' => $definition]))
            ->assertRedirect();

        $this->assertFalse($definition->refresh()->is_active);

        $this->actingAs($admin)
            ->post(route('custom-fields.store', $church), [
                'key' => 'invalid key',
                'label' => 'Inválido',
                'type' => 'text',
            ])
            ->assertSessionHasErrors('key');

        $this->actingAs($admin)
            ->post(route('custom-fields.store', $church), [
                'key' => 'empty_options',
                'label' => 'Sin opciones',
                'type' => 'select',
                'options' => [],
            ])
            ->assertSessionHasErrors('options');
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

    private function withChurchContext(Church $church, callable $callback): mixed
    {
        return app(CurrentChurch::class)->run($church, fn () => $callback());
    }
}
