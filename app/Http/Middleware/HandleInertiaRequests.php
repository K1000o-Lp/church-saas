<?php

namespace App\Http\Middleware;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\PlatformAdmin;
use App\Support\Auth\Role;
use App\Support\Auth\RolePermissions;
use App\Support\Plans\PlanCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /** @var string */
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            'platformAdmin' => fn (): ?array => $this->platformAdmin(),
            // Resolve after route middleware (`church`) has established the membership.
            'currentChurch' => fn (): ?array => $this->currentChurch($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }

    /**
     * @return array{id: int|string, name: string, email: string}|null
     */
    private function platformAdmin(): ?array
    {
        $admin = Auth::guard('platform')->user();

        return $admin instanceof PlatformAdmin ? [
            'id' => $admin->getKey(),
            'name' => $admin->name,
            'email' => $admin->email,
        ] : null;
    }

    /**
     * @return array{name: string, slug: string, countryCode: ?string, currencyCode: ?string, timezone: ?string, role: string, roleLabel: string, permissions: array<int, string>, plan: array<string, mixed>|null}|null
     */
    private function currentChurch(Request $request): ?array
    {
        $membership = $request->attributes->get('currentMembership');
        $church = $request->attributes->get('currentChurch');

        if (! $membership instanceof ChurchMembership || ! $church instanceof Church) {
            return null;
        }

        $role = Role::tryFrom((string) $membership->role);

        return [
            'name' => $church->name,
            'slug' => $church->slug,
            'countryCode' => $church->country_code,
            'currencyCode' => $church->currency_code,
            'timezone' => $church->timezone,
            'role' => $membership->role,
            'roleLabel' => $role?->label() ?? $membership->role,
            'permissions' => array_map(
                fn ($permission): string => $permission->value,
                RolePermissions::forRole($role),
            ),
            'plan' => PlanCatalog::forChurch($church),
        ];
    }
}
