<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/register'));
    }

    public function test_a_guest_can_register_and_is_sent_to_church_onboarding(): void
    {
        $response = $this->post('/register', [
            'name' => 'Ana Pérez',
            'email' => 'ANA.PEREZ@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::query()->where('email', 'ana.perez@example.com')->firstOrFail();

        $response
            ->assertRedirect(route('onboarding.church'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('Ana Pérez', $user->name);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_registration_rejects_a_duplicate_email_case_insensitively(): void
    {
        User::factory()->create(['email' => 'persona@example.com']);

        $this->post('/register', [
            'name' => 'Otra Persona',
            'email' => 'PERSONA@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(1, User::query()->count());
    }

    public function test_registration_requires_a_confirmed_password(): void
    {
        $this->post('/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_authenticated_users_are_redirected_away_from_registration(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/register')
            ->assertRedirect(route('dashboard'));
    }
}
