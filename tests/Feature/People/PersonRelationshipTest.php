<?php

namespace Tests\Feature\People;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Models\User;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_a_spouse_creates_the_reciprocal_relationship(): void
    {
        $church = $this->createChurch('iglesia-familias-conyuge');
        $admin = $this->createMember($church, 'admin');
        $ana = $this->createPerson($church, 'Ana', 'Pérez');
        $beto = $this->createPerson($church, 'Beto', 'Pérez');

        $this->actingAs($admin)
            ->post(route('people.relationships.store', [
                'church' => $church,
                'person' => $ana->getKey(),
            ]), [
                'related_person_id' => $beto->getKey(),
                'relationship' => 'spouse',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('person_relationships', [
            'church_id' => $church->getKey(),
            'person_id' => $ana->getKey(),
            'related_person_id' => $beto->getKey(),
            'relationship' => 'spouse',
        ]);
        $this->assertDatabaseHas('person_relationships', [
            'church_id' => $church->getKey(),
            'person_id' => $beto->getKey(),
            'related_person_id' => $ana->getKey(),
            'relationship' => 'spouse',
        ]);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'person.relationship_added',
            'subject_id' => $ana->getKey(),
        ]);
    }

    public function test_parent_and_child_relationships_are_inverse(): void
    {
        $church = $this->createChurch('iglesia-familias-padre');
        $admin = $this->createMember($church, 'admin');
        $parent = $this->createPerson($church, 'Carla', 'Díaz');
        $child = $this->createPerson($church, 'Diego', 'Díaz');

        $this->actingAs($admin)
            ->post(route('people.relationships.store', [
                'church' => $church,
                'person' => $parent->getKey(),
            ]), [
                'related_person_id' => $child->getKey(),
                'relationship' => 'parent',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('person_relationships', [
            'person_id' => $parent->getKey(),
            'related_person_id' => $child->getKey(),
            'relationship' => 'parent',
        ]);
        $this->assertDatabaseHas('person_relationships', [
            'person_id' => $child->getKey(),
            'related_person_id' => $parent->getKey(),
            'relationship' => 'child',
        ]);
    }

    public function test_a_person_cannot_be_related_to_themselves(): void
    {
        $church = $this->createChurch('iglesia-familias-self');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Elena', 'Mora');

        $this->actingAs($admin)
            ->post(route('people.relationships.store', [
                'church' => $church,
                'person' => $person->getKey(),
            ]), [
                'related_person_id' => $person->getKey(),
                'relationship' => 'sibling',
            ])
            ->assertSessionHasErrors('related_person_id');

        $this->assertSame(0, PersonRelationship::withoutGlobalScope('church')->count());
    }

    public function test_relationships_cannot_cross_churches(): void
    {
        $alpha = $this->createChurch('iglesia-familias-alpha');
        $admin = $this->createMember($alpha, 'admin');
        $person = $this->createPerson($alpha, 'Fabián', 'Gil');

        $beta = $this->createChurch('iglesia-familias-beta');
        $foreign = $this->createPerson($beta, 'Gina', 'López');

        $this->actingAs($admin)
            ->post(route('people.relationships.store', [
                'church' => $alpha,
                'person' => $person->getKey(),
            ]), [
                'related_person_id' => $foreign->getKey(),
                'relationship' => 'sibling',
            ])
            ->assertSessionHasErrors('related_person_id');

        $this->assertSame(0, PersonRelationship::withoutGlobalScope('church')->count());
    }

    public function test_removing_a_relationship_also_removes_the_inverse(): void
    {
        $church = $this->createChurch('iglesia-familias-eliminar');
        $admin = $this->createMember($church, 'admin');
        $ana = $this->createPerson($church, 'Ana', 'Pérez');
        $beto = $this->createPerson($church, 'Beto', 'Pérez');

        $this->actingAs($admin)
            ->post(route('people.relationships.store', [
                'church' => $church,
                'person' => $ana->getKey(),
            ]), [
                'related_person_id' => $beto->getKey(),
                'relationship' => 'spouse',
            ]);

        $entry = PersonRelationship::withoutGlobalScope('church')
            ->where('person_id', $ana->getKey())
            ->firstOrFail();

        $this->actingAs($admin)
            ->delete(route('people.relationships.destroy', [
                'church' => $church,
                'person' => $ana->getKey(),
                'relationship' => $entry->getKey(),
            ]))
            ->assertRedirect();

        $this->assertSame(0, PersonRelationship::withoutGlobalScope('church')->count());
    }

    public function test_people_viewers_without_update_cannot_manage_relationships(): void
    {
        $church = $this->createChurch('iglesia-familias-permisos');
        $leader = $this->createMember($church, 'leader');
        $ana = $this->createPerson($church, 'Ana', 'Pérez');
        $beto = $this->createPerson($church, 'Beto', 'Pérez');

        $this->actingAs($leader)
            ->post(route('people.relationships.store', [
                'church' => $church,
                'person' => $ana->getKey(),
            ]), [
                'related_person_id' => $beto->getKey(),
                'relationship' => 'sibling',
            ])
            ->assertForbidden();
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
