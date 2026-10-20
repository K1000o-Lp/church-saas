<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchInvitation;
use App\Models\ChurchMembership;
use App\Support\Audit\DomainAudit;
use App\Support\Auth\Role;
use App\Support\Auth\RolePermissions;
use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChurchMemberController extends Controller
{
    public function index(Request $request, Church $church): Response
    {
        Gate::authorize('manageMembers', $church);

        $members = ChurchMembership::query()
            ->with('user')
            ->where('church_id', $church->getKey())
            ->get()
            ->sortBy(fn (ChurchMembership $membership): string => (string) $membership->user?->name)
            ->values();

        $invitations = ChurchInvitation::query()
            ->where('church_id', $church->getKey())
            ->whereIn('status', [ChurchInvitation::STATUS_PENDING, ChurchInvitation::STATUS_EXPIRED])
            ->orderByDesc('created_at')
            ->get()
            ->each(fn (ChurchInvitation $invitation) => $invitation->markExpired())
            ->values();

        $entitlements = Entitlements::forChurch($church);
        $limit = $entitlements->limit(PlanResources::ACTIVE_USERS);

        return Inertia::render('churches/settings/members', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'members' => $members->map(fn (ChurchMembership $membership): array => [
                'id' => $membership->getKey(),
                'userId' => $membership->user_id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'role' => $membership->role,
                'roleLabel' => Role::tryFrom((string) $membership->role)?->label() ?? $membership->role,
                'status' => $membership->status,
                'joinedAt' => $membership->created_at?->toIso8601String(),
                'isCurrentUser' => (string) $membership->user_id === (string) $request->user()->getAuthIdentifier(),
            ])->all(),
            'invitations' => $invitations->map(fn (ChurchInvitation $invitation): array => [
                'id' => $invitation->getKey(),
                'email' => $invitation->email,
                'role' => $invitation->role,
                'roleLabel' => Role::tryFrom((string) $invitation->role)?->label() ?? $invitation->role,
                'status' => $invitation->status,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
                'expired' => $invitation->status === ChurchInvitation::STATUS_EXPIRED || $invitation->isExpired(),
            ])->all(),
            'roles' => RolePermissions::options(),
            'limits' => [
                'used' => $entitlements->usageFor(PlanResources::ACTIVE_USERS),
                'limit' => $limit,
            ],
        ]);
    }

    public function update(
        Request $request,
        Church $church,
        ChurchMembership $membership,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $church);

        abort_unless((string) $membership->church_id === (string) $church->getKey(), 404);

        $data = $request->validate([
            'role' => ['required', 'string', Rule::in(Role::values())],
        ]);

        if ($membership->role === $data['role']) {
            return back();
        }

        if (
            $membership->role === Role::Admin->value
            && $this->otherActiveAdmins($church, $membership) === 0
        ) {
            return back()->withErrors([
                'role' => 'La iglesia debe conservar al menos un administrador activo.',
            ]);
        }

        $before = $membership->role;
        $membership->update(['role' => $data['role']]);

        DomainAudit::recordForChurch($church, DomainAudit::MEMBERSHIP_ROLE_CHANGED, $membership, [
            'userId' => $membership->user_id,
            'from' => $before,
            'to' => $membership->role,
        ]);

        return back()->with('success', 'Rol actualizado.');
    }

    public function destroy(
        Request $request,
        Church $church,
        ChurchMembership $membership,
    ): RedirectResponse {
        Gate::authorize('manageMembers', $church);

        abort_unless((string) $membership->church_id === (string) $church->getKey(), 404);
        abort_if(
            (string) $membership->user_id === (string) $request->user()->getAuthIdentifier(),
            403,
            'No puedes eliminar tu propia membresía.',
        );

        if (
            $membership->role === Role::Admin->value
            && $this->otherActiveAdmins($church, $membership) === 0
        ) {
            return back()->withErrors([
                'member' => 'La iglesia debe conservar al menos un administrador activo.',
            ]);
        }

        DomainAudit::recordForChurch($church, DomainAudit::MEMBERSHIP_REMOVED, $membership, [
            'userId' => $membership->user_id,
            'role' => $membership->role,
        ]);

        $membership->delete();

        return back()->with('success', 'Miembro eliminado de la iglesia.');
    }

    private function otherActiveAdmins(Church $church, ChurchMembership $membership): int
    {
        return ChurchMembership::query()
            ->where('church_id', $church->getKey())
            ->where('role', Role::Admin->value)
            ->where('status', 'active')
            ->where('id', '!=', $membership->getKey())
            ->count();
    }
}
