<?php

namespace Tests\Feature\Invitations;

use App\Models\Church;
use App\Models\ChurchInvitation;
use App\Models\ChurchLimitOverride;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Notifications\ChurchInvitationNotification;
use App\Support\Plans\PlanResources;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChurchInvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_invite_a_person_with_a_hashed_token(): void
    {
        Notification::fake();

        $church = $this->createChurch('iglesia-invitacion');
        $admin = $this->createMember($church, 'admin');
        $plainToken = null;

        $this->actingAs($admin)
            ->post(route('churches.invitations.store', $church), [
                'email' => 'Invited@Example.com',
                'role' => 'leader',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $invitation = ChurchInvitation::query()->firstOrFail();

        $this->assertSame('invited@example.com', $invitation->email);
        $this->assertSame('leader', $invitation->role);
        $this->assertSame(ChurchInvitation::STATUS_PENDING, $invitation->status);
        $this->assertSame(64, strlen($invitation->token_hash));

        Notification::assertSentOnDemand(
            ChurchInvitationNotification::class,
            function (ChurchInvitationNotification $notification) use (&$plainToken): bool {
                $plainToken = $notification->plainToken;

                return true;
            },
        );

        $this->assertNotNull($plainToken);
        $this->assertNotSame($plainToken, $invitation->token_hash);
        $this->assertSame($invitation->token_hash, ChurchInvitation::hashToken($plainToken));
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'invitation.created',
        ]);
    }

    public function test_a_guest_can_register_and_accept_an_invitation(): void
    {
        $church = $this->createChurch('iglesia-aceptar-invitacion');
        $admin = $this->createMember($church, 'admin');
        $plainToken = $this->createInvitation($church, 'new@example.com', 'volunteer', $admin);

        $this->get(route('invitations.show', ['token' => $plainToken]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('invitations/show')
                ->where('requiresRegistration', true)
                ->where('invitation.email', 'new@example.com')
                ->where('invitation.status', 'pending'));

        $this->post(route('invitations.accept', ['token' => $plainToken]), [
            'name' => 'Nueva Persona',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('churches.show', $church));

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('church_memberships', [
            'church_id' => $church->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'volunteer',
            'status' => 'active',
        ]);
        $invitation = ChurchInvitation::query()->firstOrFail();

        $this->assertSame(ChurchInvitation::STATUS_ACCEPTED, $invitation->status);
        $this->assertSame(now()->toDateString(), $invitation->accepted_at?->toDateString());
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'invitation.accepted',
            'subject_id' => $invitation->getKey(),
        ]);
    }

    public function test_an_existing_authenticated_user_can_accept_an_invitation(): void
    {
        $church = $this->createChurch('iglesia-aceptar-existente');
        $admin = $this->createMember($church, 'admin');
        $user = User::factory()->create(['email' => 'member@example.com']);
        $plainToken = $this->createInvitation($church, $user->email, 'finance', $admin);

        $this->actingAs($user)
            ->post(route('invitations.accept', ['token' => $plainToken]))
            ->assertRedirect(route('churches.show', $church));

        $this->assertDatabaseHas('church_memberships', [
            'church_id' => $church->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'finance',
            'status' => 'active',
        ]);
    }

    public function test_an_invitation_is_bound_to_its_email(): void
    {
        $church = $this->createChurch('iglesia-invitacion-email');
        $admin = $this->createMember($church, 'admin');
        $other = User::factory()->create(['email' => 'other@example.com']);
        $plainToken = $this->createInvitation($church, 'expected@example.com', 'member', $admin);

        $this->actingAs($other)
            ->get(route('invitations.show', ['token' => $plainToken]))
            ->assertForbidden();

        $this->actingAs($other)
            ->post(route('invitations.accept', ['token' => $plainToken]))
            ->assertSessionHasErrors('invitation');
    }

    public function test_an_expired_invitation_cannot_be_used(): void
    {
        $church = $this->createChurch('iglesia-invitacion-vencida');
        $admin = $this->createMember($church, 'admin');
        $plainToken = $this->createInvitation(
            $church,
            'expired@example.com',
            'member',
            $admin,
            expiresAt: now()->subDay(),
        );

        $this->get(route('invitations.show', ['token' => $plainToken]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('invitation.status', ChurchInvitation::STATUS_EXPIRED));

        $this->post(route('invitations.accept', ['token' => $plainToken]), [
            'name' => 'Expired',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('invitation');
    }

    public function test_an_invitation_can_only_be_accepted_once(): void
    {
        $church = $this->createChurch('iglesia-invitacion-un-uso');
        $admin = $this->createMember($church, 'admin');
        $plainToken = $this->createInvitation($church, 'once@example.com', 'member', $admin);

        $this->post(route('invitations.accept', ['token' => $plainToken]), [
            'name' => 'Once',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->post(route('invitations.accept', ['token' => $plainToken]), [
            'name' => 'Once Again',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('invitation');
    }

    public function test_non_admins_cannot_send_invitations(): void
    {
        $church = $this->createChurch('iglesia-invitacion-permisos');

        foreach (['pastor', 'finance', 'leader', 'volunteer', 'member'] as $role) {
            $user = $this->createMember($church, $role);

            $this->actingAs($user)
                ->post(route('churches.invitations.store', $church), [
                    'email' => "{$role}@example.com",
                    'role' => 'member',
                ])
                ->assertForbidden();
        }
    }

    public function test_invitation_validation_rejects_unknown_roles_and_seat_exhaustion(): void
    {
        $church = $this->createChurch('iglesia-invitacion-validacion');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->post(route('churches.invitations.store', $church), [
                'email' => 'role@example.com',
                'role' => 'superadmin',
            ])
            ->assertSessionHasErrors('role');

        ChurchLimitOverride::create([
            'church_id' => $church->getKey(),
            'resource' => PlanResources::ACTIVE_USERS,
            'limit_value' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('churches.invitations.store', $church), [
                'email' => 'seat@example.com',
                'role' => 'member',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_only_the_last_admin_is_protected_from_demotion_and_removal(): void
    {
        $church = $this->createChurch('iglesia-ultimo-admin');
        $admin = $this->createMember($church, 'admin');
        $adminMembership = ChurchMembership::query()->where('user_id', $admin->getKey())->firstOrFail();

        $this->actingAs($admin)
            ->put(route('churches.members.update', ['church' => $church, 'membership' => $adminMembership]), [
                'role' => 'leader',
            ])
            ->assertSessionHasErrors('role');

        $this->actingAs($admin)
            ->delete(route('churches.members.destroy', ['church' => $church, 'membership' => $adminMembership]))
            ->assertForbidden();

        $secondAdmin = $this->createMember($church, 'admin');
        $secondMembership = ChurchMembership::query()->where('user_id', $secondAdmin->getKey())->firstOrFail();

        $this->actingAs($admin)
            ->put(route('churches.members.update', ['church' => $church, 'membership' => $secondMembership]), [
                'role' => 'pastor',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'membership.role_changed',
        ]);
    }

    private function createInvitation(
        Church $church,
        string $email,
        string $role,
        User $invitedBy,
        mixed $expiresAt = null,
    ): string {
        [$plain, $hash] = ChurchInvitation::newToken();

        ChurchInvitation::create([
            'church_id' => $church->getKey(),
            'email' => strtolower($email),
            'role' => $role,
            'token_hash' => $hash,
            'status' => ChurchInvitation::STATUS_PENDING,
            'invited_by' => $invitedBy->getKey(),
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);

        return $plain;
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
