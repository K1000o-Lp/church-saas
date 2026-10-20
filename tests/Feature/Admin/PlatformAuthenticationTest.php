<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PlatformAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_platform_login(): void
    {
        $this->get('/admin/churches')->assertRedirect(route('admin.login'));
    }

    public function test_the_platform_login_screen_can_be_rendered(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/login'));
    }

    public function test_an_active_platform_admin_can_login(): void
    {
        $admin = PlatformAdmin::factory()->create([
            'password' => 'password',
        ]);

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($admin, 'platform');
        $response->assertRedirect(route('admin.churches.index'));

        $this->assertNotNull($admin->refresh()->last_login_at);
        $this->assertDatabaseHas('platform_audit_entries', [
            'platform_admin_id' => $admin->getKey(),
            'action' => 'platform.login',
        ]);
    }

    public function test_an_inactive_platform_admin_cannot_login(): void
    {
        $admin = PlatformAdmin::factory()->inactive()->create([
            'password' => 'password',
        ]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('platform');
    }

    public function test_a_church_user_cannot_access_the_platform_backoffice(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/churches')
            ->assertRedirect(route('admin.login'));
    }

    public function test_a_platform_admin_is_not_authenticated_for_church_routes(): void
    {
        $admin = PlatformAdmin::factory()->create(['password' => 'password']);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.churches.index'));

        $this->get('/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest('web');
    }

    public function test_platform_login_ends_any_church_session(): void
    {
        $user = User::factory()->create();
        $admin = PlatformAdmin::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->withSession(['current_church_id' => 123])
            ->post('/admin/login', [
                'email' => $admin->email,
                'password' => 'password',
            ])
            ->assertRedirect(route('admin.churches.index'));

        $this->assertGuest('web');
        $this->assertAuthenticatedAs($admin, 'platform');
        $this->assertNull(session('current_church_id'));
    }

    public function test_a_platform_admin_can_logout(): void
    {
        $admin = PlatformAdmin::factory()->create();

        $this->actingAs($admin, 'platform')
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('platform');
    }
}
