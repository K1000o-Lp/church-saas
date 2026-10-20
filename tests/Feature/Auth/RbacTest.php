<?php

namespace Tests\Feature\Auth;

use App\Models\Church;
use App\Models\ChurchAuditEntry;
use App\Models\ChurchMembership;
use App\Models\Person;
use App\Models\User;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shared_church_context_exposes_permissions(): void
    {
        $church = $this->createChurch('iglesia-rbac-permisos');
        $user = $this->createMember($church, 'leader');

        $this->actingAs($user)
            ->get(route('churches.show', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('currentChurch.role', 'leader')
                ->where('currentChurch.roleLabel', 'Líder')
                ->where('currentChurch.permissions', fn ($permissions) => collect($permissions)->contains('people.view')
                    && ! collect($permissions)->contains('church.members.manage')));
    }

    public function test_roles_gate_people_routes(): void
    {
        $church = $this->createChurch('iglesia-rbac-personas');
        $this->withChurchContext($church, fn () => Person::create(['first_name' => 'Ana']));

        $admin = $this->createMember($church, 'admin');
        $pastor = $this->createMember($church, 'pastor');
        $leader = $this->createMember($church, 'leader');
        $finance = $this->createMember($church, 'finance');
        $member = $this->createMember($church, 'member');

        $this->actingAs($admin)->get(route('people.index', $church))->assertOk();
        $this->actingAs($pastor)->get(route('people.create', $church))->assertOk();
        $this->actingAs($leader)->get(route('people.index', $church))->assertOk();
        $this->actingAs($leader)->get(route('people.create', $church))->assertForbidden();
        $this->actingAs($finance)->get(route('people.index', $church))->assertForbidden();
        $this->actingAs($member)->get(route('people.index', $church))->assertForbidden();
    }

    public function test_member_management_requires_the_members_permission(): void
    {
        $church = $this->createChurch('iglesia-rbac-miembros');
        $admin = $this->createMember($church, 'admin');
        $finance = $this->createMember($church, 'finance');

        $this->actingAs($admin)->get(route('churches.members.index', $church))->assertOk();
        $this->actingAs($finance)->get(route('churches.members.index', $church))->assertForbidden();
    }

    public function test_a_user_without_membership_cannot_use_people_routes(): void
    {
        $church = $this->createChurch('iglesia-rbac-sin-membresia');
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('people.index', $church))
            ->assertNotFound();
    }

    public function test_only_admins_can_view_the_domain_audit_log(): void
    {
        $church = $this->createChurch('iglesia-rbac-auditoria');
        $admin = $this->createMember($church, 'admin');
        $finance = $this->createMember($church, 'finance');

        ChurchAuditEntry::create([
            'church_id' => $church->getKey(),
            'action' => 'person.created',
            'subject_type' => Person::class,
            'subject_id' => 1,
            'metadata' => ['firstName' => 'Ana'],
        ]);

        $this->actingAs($admin)
            ->get(route('churches.audit.index', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('churches/settings/audit')
                ->has('entries', 1)
                ->where('entries.0.action', 'person.created'));

        $this->actingAs($finance)
            ->get(route('churches.audit.index', $church))
            ->assertForbidden();
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
