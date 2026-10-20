<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión');
    }

    public function test_users_can_authenticate_with_valid_credentials(): void
    {
        $user = $this->createUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_users_cannot_authenticate_with_invalid_credentials(): void
    {
        $user = $this->createUser();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_users_can_logout(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guests_are_redirected_to_login_from_the_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_members_with_a_single_church_are_redirected_to_it(): void
    {
        $user = $this->createUser();
        $church = $this->createChurch('iglesia-unica');
        $this->createMembership($church, $user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('churches.show', $church));
    }

    public function test_members_without_active_churches_see_the_empty_state(): void
    {
        $user = $this->createUser();
        $church = $this->createChurch('iglesia-inactiva-dashboard', 'inactive');
        $this->createMembership($church, $user);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('No tienes membresías activas');
    }

    public function test_a_member_can_access_an_active_church(): void
    {
        $user = $this->createUser();
        $church = $this->createChurch('iglesia-central');
        $this->createMembership($church, $user);

        $this->actingAs($user)
            ->get(route('churches.show', $church))
            ->assertOk()
            ->assertSee($church->name)
            ->assertSessionHas('current_church_id', $church->id);
    }

    public function test_a_user_without_membership_cannot_access_a_church(): void
    {
        $user = $this->createUser();
        $church = $this->createChurch('iglesia-privada');

        $this->actingAs($user)
            ->get(route('churches.show', $church))
            ->assertNotFound();
    }

    public function test_a_suspended_membership_cannot_access_a_church(): void
    {
        $user = $this->createUser();
        $church = $this->createChurch('iglesia-suspendida');
        $this->createMembership($church, $user, 'suspended');

        $this->actingAs($user)
            ->get(route('churches.show', $church))
            ->assertNotFound();
    }

    public function test_an_inactive_church_cannot_be_accessed(): void
    {
        $user = $this->createUser();
        $church = $this->createChurch('iglesia-inactiva', 'inactive');
        $this->createMembership($church, $user);

        $this->actingAs($user)
            ->get(route('churches.show', $church))
            ->assertNotFound();
    }

    public function test_church_routes_use_the_slug_as_the_key(): void
    {
        $church = $this->createChurch('comunidad-esperanza');

        $this->assertSame(
            url('/churches/comunidad-esperanza'),
            route('churches.show', $church)
        );
    }

    public function test_the_session_church_is_resolved_when_no_route_church_is_present(): void
    {
        $user = $this->createUser();
        $church = $this->createChurch('iglesia-sesion');
        $this->createMembership($church, $user);

        Route::middleware(['web', 'auth', 'church'])->get('/tenant-context', function (Request $request) {
            return response()->json([
                'church_id' => app(CurrentChurch::class)->id(),
                'membership_id' => $request->attributes->get('currentMembership')->id,
            ]);
        });

        $this->actingAs($user)
            ->withSession(['current_church_id' => $church->id])
            ->get('/tenant-context')
            ->assertOk()
            ->assertJson(['church_id' => $church->id]);
    }

    private function createUser(): User
    {
        return User::factory()->create([
            'password' => Hash::make('password'),
        ]);
    }

    private function createChurch(string $slug, string $status = 'active'): Church
    {
        return Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => $status,
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
        ]);
    }

    private function createMembership(Church $church, User $user, string $status = 'active'): ChurchMembership
    {
        return ChurchMembership::create([
            'church_id' => $church->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => $status,
        ]);
    }
}
