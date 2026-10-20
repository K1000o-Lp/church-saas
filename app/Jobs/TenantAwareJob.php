<?php

namespace App\Jobs;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Support\Church\ChurchStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;

/**
 * Base class for queued work that touches tenant-owned data.
 *
 * Jobs must carry tenant identifiers, never Eloquent models. The tenant,
 * church status and (when present) the actor membership are revalidated on
 * execution so a revoked membership or disabled church fails closed.
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct(
        public readonly int $churchId,
        public readonly ?int $actorUserId = null,
        public readonly ?string $permission = null,
    ) {}

    final public function handle(CurrentChurch $currentChurch): void
    {
        $church = Church::query()->find($this->churchId);

        if (! $church instanceof Church) {
            throw new RuntimeException('The tenant church no longer exists.');
        }

        if (ChurchStatus::blocksAccess($church->status)) {
            throw new RuntimeException('The tenant church is not active.');
        }

        $membership = null;

        if ($this->actorUserId !== null) {
            $membership = ChurchMembership::query()
                ->where('church_id', $church->getKey())
                ->where('user_id', $this->actorUserId)
                ->where('status', 'active')
                ->first();

            if (! $membership instanceof ChurchMembership) {
                throw new AuthorizationException('The job actor no longer has access to the church.');
            }
        }

        $currentChurch->run($church, function () use ($currentChurch, $church, $membership): void {
            if ($this->permission !== null && ! $currentChurch->allows($this->permission)) {
                throw new AuthorizationException('The job actor no longer has the required permission.');
            }

            $this->handleForChurch($church, $membership);
        }, $membership);
    }

    abstract protected function handleForChurch(Church $church, ?ChurchMembership $membership): void;
}
