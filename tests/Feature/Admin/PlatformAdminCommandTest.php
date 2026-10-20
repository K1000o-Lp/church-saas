<?php

namespace Tests\Feature\Admin;

use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_creates_an_exclusive_platform_admin(): void
    {
        $this->artisan('platform:admin:create', [
            'email' => 'Admin@Example.com',
            '--name' => 'Admin Plataforma',
            '--password' => 'super-secret-password',
        ])->assertExitCode(0);

        $admin = PlatformAdmin::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('Admin Plataforma', $admin->name);
        $this->assertTrue($admin->is_active);
    }

    public function test_the_command_rejects_an_email_used_by_a_church_user(): void
    {
        User::factory()->create(['email' => 'persona@iglesia.test']);

        $this->artisan('platform:admin:create', [
            'email' => 'persona@iglesia.test',
            '--name' => 'Admin Plataforma',
            '--password' => 'super-secret-password',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('platform_admins', [
            'email' => 'persona@iglesia.test',
        ]);
    }

    public function test_the_command_rejects_a_duplicate_platform_admin(): void
    {
        PlatformAdmin::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('platform:admin:create', [
            'email' => 'admin@example.com',
            '--name' => 'Admin Duplicado',
            '--password' => 'super-secret-password',
        ])->assertExitCode(1);
    }

    public function test_the_deactivate_command_disables_the_account(): void
    {
        $admin = PlatformAdmin::factory()->create(['email' => 'admin@example.com']);

        $this->artisan('platform:admin:deactivate', [
            'email' => 'admin@example.com',
        ])->assertExitCode(0);

        $this->assertFalse($admin->refresh()->is_active);
    }
}
