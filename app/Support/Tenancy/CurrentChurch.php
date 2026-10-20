<?php

namespace App\Support\Tenancy;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Support\Auth\Permission;
use App\Support\Auth\Role;
use App\Support\Auth\RolePermissions;
use Closure;
use RuntimeException;

class CurrentChurch
{
    private ?Church $church = null;

    private ?ChurchMembership $membership = null;

    public function set(Church $church, ?ChurchMembership $membership = null): void
    {
        $this->church = $church;
        $this->membership = $membership;
    }

    public function church(): ?Church
    {
        return $this->church;
    }

    public function membership(): ?ChurchMembership
    {
        return $this->membership;
    }

    public function role(): ?string
    {
        return $this->membership?->role;
    }

    public function id(): int|string|null
    {
        return $this->church?->getKey();
    }

    public function allows(Permission|string $permission): bool
    {
        $membership = $this->membership;

        if ($membership === null || $membership->status !== 'active') {
            return false;
        }

        return RolePermissions::allows(
            Role::tryFrom((string) $membership->role),
            $permission,
        );
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return array_map(
            fn (Permission $permission): string => $permission->value,
            RolePermissions::forRole(
                $this->membership === null ? null : Role::tryFrom((string) $this->membership->role),
            ),
        );
    }

    /**
     * Run a callback with an explicit tenant context. The previous context is
     * restored afterwards, including when the callback throws.
     *
     * @template TReturn
     *
     * @param  Closure(Church, ?ChurchMembership): TReturn  $callback
     * @return TReturn
     */
    public function run(Church|int $church, Closure $callback, ?ChurchMembership $membership = null): mixed
    {
        if (is_int($church)) {
            $church = Church::query()->find($church);

            if (! $church instanceof Church) {
                throw new RuntimeException('The tenant church does not exist.');
            }
        }

        $previousChurch = $this->church;
        $previousMembership = $this->membership;

        $this->set($church, $membership);

        try {
            return $callback($church, $membership);
        } finally {
            $this->church = $previousChurch;
            $this->membership = $previousMembership;
        }
    }

    public function clear(): void
    {
        $this->church = null;
        $this->membership = null;
    }
}
