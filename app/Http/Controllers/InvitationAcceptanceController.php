<?php

namespace App\Http\Controllers;

use App\Exceptions\PlanLimitExceededException;
use App\Http\Requests\AcceptChurchInvitationRequest;
use App\Models\Church;
use App\Models\ChurchInvitation;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\Audit\DomainAudit;
use App\Support\Church\ChurchStatus;
use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptanceController extends Controller
{
    public function show(Request $request, string $token): Response|RedirectResponse
    {
        $invitation = $this->findByToken($token);

        abort_if($invitation === null, 404);

        $invitation->markExpired();

        $church = $invitation->church;

        $props = [
            'token' => $token,
            'invitation' => [
                'churchName' => $church?->name,
                'churchSlug' => $church?->slug,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
            ],
            'requiresRegistration' => false,
            'isAuthenticated' => $request->user() !== null,
        ];

        if ($invitation->status !== ChurchInvitation::STATUS_PENDING) {
            return Inertia::render('invitations/show', $props);
        }

        if ($church === null || ChurchStatus::blocksAccess($church->status)) {
            return Inertia::render('invitations/show', [
                ...$props,
                'invitation' => [
                    ...$props['invitation'],
                    'status' => 'church_unavailable',
                ],
            ]);
        }

        $existingUser = User::query()
            ->whereRaw('lower(email) = ?', [Str::lower($invitation->email)])
            ->first();

        if ($request->user() !== null) {
            abort_unless(
                Str::lower((string) $request->user()->email) === Str::lower($invitation->email),
                403,
                'Esta invitación pertenece a otro correo electrónico.',
            );

            return Inertia::render('invitations/show', $props);
        }

        if ($existingUser !== null) {
            return redirect()->guest(route('login'));
        }

        return Inertia::render('invitations/show', [
            ...$props,
            'requiresRegistration' => true,
        ]);
    }

    public function store(
        AcceptChurchInvitationRequest $request,
        string $token,
    ): RedirectResponse {
        $invitation = $this->findByToken($token);

        abort_if($invitation === null, 404);

        $validated = $request->validated();
        $authenticatedUser = $request->user();

        try {
            [$user, $church] = DB::transaction(function () use ($invitation, $validated, $authenticatedUser): array {
                /** @var ChurchInvitation $locked */
                $locked = ChurchInvitation::query()
                    ->whereKey($invitation->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $locked->markExpired();

                if ($locked->status !== ChurchInvitation::STATUS_PENDING) {
                    throw ValidationException::withMessages([
                        'invitation' => 'Esta invitación ya no está disponible.',
                    ]);
                }

                $church = $locked->church;

                if (! $church instanceof Church || ChurchStatus::blocksAccess($church->status)) {
                    throw ValidationException::withMessages([
                        'invitation' => 'La iglesia no está disponible en este momento.',
                    ]);
                }

                if ($authenticatedUser === null) {
                    $existing = User::query()
                        ->whereRaw('lower(email) = ?', [Str::lower($locked->email)])
                        ->first();

                    if ($existing instanceof User) {
                        throw ValidationException::withMessages([
                            'invitation' => 'Ya existe una cuenta con este correo. Inicia sesión para aceptar la invitación.',
                        ]);
                    }

                    $user = User::create([
                        'name' => $validated['name'],
                        'email' => $locked->email,
                        'password' => $validated['password'],
                        'email_verified_at' => now(),
                    ]);
                } else {
                    $user = $authenticatedUser;

                    if (Str::lower((string) $user->email) !== Str::lower($locked->email)) {
                        throw ValidationException::withMessages([
                            'invitation' => 'Esta invitación pertenece a otro correo electrónico.',
                        ]);
                    }
                }

                $membership = ChurchMembership::query()
                    ->where('church_id', $church->getKey())
                    ->where('user_id', $user->getKey())
                    ->first();

                $entitlements = Entitlements::forChurch($church);
                $limit = $entitlements->limit(PlanResources::ACTIVE_USERS);

                if ($membership instanceof ChurchMembership && $membership->status === 'active') {
                    throw ValidationException::withMessages([
                        'invitation' => 'Ya perteneces a esta iglesia.',
                    ]);
                }

                if (
                    $membership === null
                    && $limit !== null
                    && $entitlements->usageFor(PlanResources::ACTIVE_USERS) >= $limit
                ) {
                    throw ValidationException::withMessages([
                        'invitation' => 'El plan actual no tiene asientos disponibles para otro usuario.',
                    ]);
                }

                if ($membership instanceof ChurchMembership) {
                    $membership->update([
                        'role' => $locked->role,
                        'status' => 'active',
                    ]);
                } else {
                    $membership = ChurchMembership::create([
                        'church_id' => $church->getKey(),
                        'user_id' => $user->getKey(),
                        'role' => $locked->role,
                        'status' => 'active',
                    ]);
                }

                $locked->forceFill([
                    'status' => ChurchInvitation::STATUS_ACCEPTED,
                    'accepted_by' => $user->getKey(),
                    'accepted_at' => now(),
                ])->save();

                DomainAudit::recordForChurch(
                    $church,
                    DomainAudit::INVITATION_ACCEPTED,
                    $locked,
                    [
                        'email' => $locked->email,
                        'role' => $locked->role,
                        'membershipId' => $membership->getKey(),
                    ],
                    $user,
                );

                return [$user, $church];
            });
        } catch (PlanLimitExceededException $exception) {
            throw ValidationException::withMessages([
                'invitation' => $exception->getMessage(),
            ]);
        }

        Auth::guard('web')->login($user);

        $request->session()->regenerate();
        $request->session()->put('current_church_id', $church->getKey());

        return redirect()
            ->route('churches.show', $church)
            ->with('success', 'Invitación aceptada. Bienvenido a '.$church->name.'.');
    }

    private function findByToken(string $token): ?ChurchInvitation
    {
        if ($token === '') {
            return null;
        }

        return ChurchInvitation::query()
            ->with('church')
            ->where('token_hash', ChurchInvitation::hashToken($token))
            ->first();
    }
}
