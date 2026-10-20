<?php

namespace Tests\Unit;

use App\Models\Church;
use App\Models\Person;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\TestCase;

class BelongsToChurchScopeTest extends TestCase
{
    public function test_scope_adds_the_current_church_predicate_and_binding(): void
    {
        $context = app(CurrentChurch::class);
        $church = new Church;
        $church->setAttribute('id', 17);
        $context->set($church);

        $query = Person::query();

        $this->assertStringContainsString('church_id" = ?', $query->toSql());
        $this->assertSame([17], $query->getBindings());
    }

    public function test_scope_without_a_tenant_is_always_false(): void
    {
        app(CurrentChurch::class)->clear();

        $query = Person::query();

        $this->assertStringContainsString('1 = 0', $query->toSql());
        $this->assertSame([], $query->getBindings());
    }

    public function test_create_update_and_delete_without_context_are_rejected_before_database_access(): void
    {
        app(CurrentChurch::class)->clear();

        try {
            (new Person)->save();
            $this->fail('Creating a tenant-owned record without context should be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $person = $this->existingPerson(17);

        try {
            $person->setAttribute('first_name', 'Changed');
            $person->save();
            $this->fail('Updating a tenant-owned record without context should be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        try {
            $person->delete();
            $this->fail('Deleting a tenant-owned record without context should be rejected.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
    }

    public function test_delete_from_another_church_is_rejected_before_database_access(): void
    {
        $church = new Church;
        $church->setAttribute('id', 18);
        app(CurrentChurch::class)->set($church);

        $person = $this->existingPerson(17);

        $this->expectException(AuthorizationException::class);

        $person->delete();
    }

    public function test_church_id_cannot_be_mutated(): void
    {
        $church = new Church;
        $church->setAttribute('id', 17);
        app(CurrentChurch::class)->set($church);

        $person = $this->existingPerson(17);
        $person->setAttribute('church_id', 18);

        $this->expectException(AuthorizationException::class);

        $person->save();
    }

    private function existingPerson(int $churchId): Person
    {
        $person = new Person;
        $person->setRawAttributes(['id' => 1, 'church_id' => $churchId, 'first_name' => 'Test']);
        $person->exists = true;
        $person->syncOriginal();

        return $person;
    }
}
