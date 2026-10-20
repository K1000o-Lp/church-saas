<?php

namespace Tests\Feature\Tenancy;

use App\Jobs\TenantAwareJob;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TenantAwareJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_job_runs_with_tenant_context_and_clears_it_afterwards(): void
    {
        $church = $this->createChurch('iglesia-job-contexto');
        $admin = $this->createMember($church, 'admin');

        $job = new ProbeTenantJob($church->getKey(), $admin->getKey(), 'people.create');

        $job->handle(app(CurrentChurch::class));

        $this->assertSame($church->getKey(), $job->seenChurchId);
        $this->assertSame($church->getKey(), $job->seenContextId);
        $this->assertSame('admin', $job->seenRole);
        $this->assertNull(app(CurrentChurch::class)->id());
    }

    public function test_context_is_cleared_when_the_job_throws(): void
    {
        $church = $this->createChurch('iglesia-job-excepcion');
        $job = new ProbeTenantJob($church->getKey(), throw: true);

        try {
            $job->handle(app(CurrentChurch::class));
            $this->fail('The probe job should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('probe failure', $exception->getMessage());
        }

        $this->assertNull(app(CurrentChurch::class)->id());
    }

    public function test_a_job_cannot_run_without_a_valid_actor_or_permission(): void
    {
        $church = $this->createChurch('iglesia-job-autorizacion');
        $member = $this->createMember($church, 'member');

        $this->expectException(AuthorizationException::class);

        (new ProbeTenantJob($church->getKey(), $member->getKey(), 'people.create'))
            ->handle(app(CurrentChurch::class));
    }

    public function test_a_job_rejects_inactive_churches_and_missing_actors(): void
    {
        $church = $this->createChurch('iglesia-job-inactiva', 'inactive');

        $this->expectException(RuntimeException::class);

        (new ProbeTenantJob($church->getKey()))->handle(app(CurrentChurch::class));
    }

    public function test_a_job_rejects_an_actor_without_an_active_membership(): void
    {
        $church = $this->createChurch('iglesia-job-sin-membresia');
        $outsider = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        (new ProbeTenantJob($church->getKey(), $outsider->getKey()))
            ->handle(app(CurrentChurch::class));
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

class ProbeTenantJob extends TenantAwareJob
{
    public ?int $seenChurchId = null;

    public mixed $seenContextId = null;

    public ?string $seenRole = null;

    public function __construct(
        int $churchId,
        ?int $actorUserId = null,
        ?string $permission = null,
        private readonly bool $throw = false,
    ) {
        parent::__construct($churchId, $actorUserId, $permission);
    }

    protected function handleForChurch(Church $church, ?ChurchMembership $membership): void
    {
        $this->seenChurchId = $church->getKey();
        $this->seenContextId = app(CurrentChurch::class)->id();
        $this->seenRole = $membership?->role;

        if ($this->throw) {
            throw new RuntimeException('probe failure');
        }
    }
}
