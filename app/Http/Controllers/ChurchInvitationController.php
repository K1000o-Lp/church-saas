<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChurchInvitationRequest;
use App\Models\Church;
use App\Models\ChurchInvitation;
use App\Models\ChurchMembership;
use App\Models\PlatformAdmin;
use App\Notifications\ChurchInvitationNotification;
use App\Support\Audit\DomainAudit;
use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanResources;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChurchInvitationController extends Controller
{
    public function store(
        StoreChurchInvitationRequest $request,
        Church $church,
    ): RedirectResponse {
        Gate::authorize('create', ChurchInvitation::class);

        $data = $request->validated();
        $email = Str::lower($data['email']);

        if (PlatformAdmin::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Ese correo pertenece a una cuenta de plataforma y no puede unirse a una iglesia.',
            ]);
        }

        $isMember = ChurchMembership::query()
            ->where('church_id', $church->getKey())
            ->whereHas('user', fn ($query) => $query->whereRaw('lower(email) = ?', [$email]))
            ->exists();

        if ($isMember) {
            throw ValidationException::withMessages([
                'email' => 'Ese correo ya pertenece a un miembro de la iglesia.',
            ]);
        }

        $pending = ChurchInvitation::query()
            ->where('church_id', $church->getKey())
            ->whereRaw('lower(email) = ?', [$email])
            ->where('status', ChurchInvitation::STATUS_PENDING)
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'email' => 'Ya hay una invitación pendiente para ese correo.',
            ]);
        }

        $entitlements = Entitlements::forChurch($church);
        $limit = $entitlements->limit(PlanResources::ACTIVE_USERS);

        if ($limit !== null && $entitlements->usageFor(PlanResources::ACTIVE_USERS) >= $limit) {
            throw ValidationException::withMessages([
                'email' => 'El plan actual no tiene asientos disponibles para otro usuario. Mejora el plan o libera un asiento.',
            ]);
        }

        [$plainToken, $tokenHash] = ChurchInvitation::newToken();

        $invitation = DB::transaction(function () use ($church, $email, $data, $tokenHash, $request): ChurchInvitation {
            $invitation = ChurchInvitation::create([
                'church_id' => $church->getKey(),
                'email' => $email,
                'role' => $data['role'],
                'token_hash' => $tokenHash,
                'status' => ChurchInvitation::STATUS_PENDING,
                'invited_by' => $request->user()->getAuthIdentifier(),
                'expires_at' => now()->addDays(7),
            ]);

            DomainAudit::record(
                DomainAudit::INVITATION_CREATED,
                $invitation,
                ['email' => $email, 'role' => $invitation->role],
            );

            return $invitation;
        });

        try {
            Notification::route('mail', $email)
                ->notify(new ChurchInvitationNotification($invitation, $plainToken));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'La invitación quedó registrada, pero el correo no se pudo enviar. Usa reenviar para intentarlo de nuevo.',
            );
        }

        return back()->with('success', 'Invitación enviada a '.$email.'.');
    }

    public function resend(
        Request $request,
        Church $church,
        ChurchInvitation $invitation,
    ): RedirectResponse {
        Gate::authorize('update', $invitation);

        abort_unless((string) $invitation->church_id === (string) $church->getKey(), 404);

        if ($invitation->status !== ChurchInvitation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invitation' => 'Solo puedes reenviar invitaciones pendientes.',
            ]);
        }

        [$plainToken, $tokenHash] = ChurchInvitation::newToken();

        DB::transaction(function () use ($invitation, $tokenHash): void {
            $invitation->forceFill([
                'token_hash' => $tokenHash,
                'expires_at' => now()->addDays(7),
            ])->save();

            DomainAudit::record(
                DomainAudit::INVITATION_CREATED,
                $invitation,
                ['email' => $invitation->email, 'role' => $invitation->role, 'resent' => true],
            );
        });

        try {
            Notification::route('mail', $invitation->email)
                ->notify(new ChurchInvitationNotification($invitation, $plainToken));
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'El enlace se renovó, pero el correo no se pudo enviar. Usa reenviar para intentarlo de nuevo.',
            );
        }

        return back()->with('success', 'Invitación reenviada.');
    }

    public function destroy(
        Request $request,
        Church $church,
        ChurchInvitation $invitation,
    ): RedirectResponse {
        Gate::authorize('delete', $invitation);

        abort_unless((string) $invitation->church_id === (string) $church->getKey(), 404);

        if ($invitation->status !== ChurchInvitation::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'invitation' => 'Solo puedes revocar invitaciones pendientes.',
            ]);
        }

        DB::transaction(function () use ($invitation): void {
            $invitation->forceFill([
                'status' => ChurchInvitation::STATUS_REVOKED,
                'revoked_at' => now(),
            ])->save();

            DomainAudit::record(
                DomainAudit::INVITATION_REVOKED,
                $invitation,
                ['email' => $invitation->email, 'role' => $invitation->role],
            );
        });

        return back()->with('success', 'Invitación revocada.');
    }
}
