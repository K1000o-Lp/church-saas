<?php

namespace Tests\Feature\Auth;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    public function test_a_guest_can_register_and_is_sent_to_email_verification(): void
    {
        Notification::fake();

        $response = $this->post('/register', [
            'name' => 'Ana Pérez',
            'email' => 'ANA.PEREZ@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $user = User::query()->where('email', 'ana.perez@example.com')->firstOrFail();

        $response
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($user);
        $this->assertSame('Ana Pérez', $user->name);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_users_cannot_access_onboarding(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/onboarding/church')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_registration_rejects_a_platform_admin_email(): void
    {
        PlatformAdmin::factory()->create(['email' => 'platform@example.com']);

        $this->post('/register', [
            'name' => 'Otra Persona',
            'email' => 'platform@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
    }

    public function test_registration_requires_a_strong_password(): void
    {
        $this->post('/register', [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'password' => 'onlyletters',
            'password_confirmation' => 'onlyletters',
        ])->assertSessionHasErrors('password');

        $this->assertGuest();
        $this->assertSame(0, User::query()->count());
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
