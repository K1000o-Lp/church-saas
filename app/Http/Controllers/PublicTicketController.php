<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\EventRegistration;
use App\Support\Events\EventStatus;
use App\Support\Events\PaymentStatus;
use App\Support\Events\RegistrationStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicTicketController extends Controller
{
    public function show(string $token): Response
    {
        $base = EventRegistration::withoutGlobalScope('church')
            ->where('public_token_hash', hash('sha256', $token))
            ->firstOrFail();

        $church = Church::query()->findOrFail($base->church_id);

        $registration = app(CurrentChurch::class)->run($church, fn (): EventRegistration => EventRegistration::query()
            ->with(['event', 'person'])
            ->findOrFail($base->getKey()));

        abort_unless($registration->issued_at !== null, 404);
        abort_unless(in_array($registration->status, [
            RegistrationStatus::Registered->value,
            RegistrationStatus::Confirmed->value,
        ], true), 404);

        $branding = $church->settings['branding'] ?? [];
        $logoPath = $branding['logo_path'] ?? null;
        $ticketUrl = route('public.ticket.show', ['token' => $token]);

        return Inertia::render('public/ticket', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
                'tagline' => $branding['tagline'] ?? null,
                'logoUrl' => $logoPath === null ? null : Storage::disk('public')->url($logoPath),
            ],
            'event' => [
                'id' => $registration->event->getKey(),
                'title' => $registration->event->title,
                'slug' => $registration->event->slug,
                'startsAt' => $registration->event->starts_at?->toIso8601String(),
                'endsAt' => $registration->event->ends_at?->toIso8601String(),
                'location' => $registration->event->location,
                'status' => $registration->event->status,
                'statusLabel' => EventStatus::label($registration->event->status),
            ],
            'attendee' => [
                'name' => $registration->person === null
                    ? $registration->guest_name
                    : trim($registration->person->first_name.' '.($registration->person->last_name ?? '')),
                'guestCount' => $registration->guest_count,
                'paymentStatus' => $registration->payment_status,
                'paymentStatusLabel' => PaymentStatus::label($registration->payment_status),
            ],
            'ticketUrl' => $ticketUrl,
        ]);
    }
}
