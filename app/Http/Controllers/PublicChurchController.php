<?php

namespace App\Http\Controllers;

use App\Exceptions\PlanLimitExceededException;
use App\Http\Requests\PublicEventRegistrationRequest;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Person;
use App\Support\Audit\DomainAudit;
use App\Support\Events\PaymentStatus;
use App\Support\Events\RegistrationStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PublicChurchController extends Controller
{
    public function show(Church $church): Response
    {
        $this->ensurePublic($church);

        $events = app(CurrentChurch::class)->run($church, fn () => Event::query()
            ->where('status', 'published')
            ->where('is_public', true)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '>=', now()->subDay());
            })
            ->orderBy('starts_at')
            ->limit(20)
            ->get()
            ->map(fn (Event $event): array => $this->eventSummary($event))
            ->values()
            ->all());

        return Inertia::render('public/church', [
            'church' => $this->churchPayload($church),
            'events' => $events,
        ]);
    }

    public function event(Church $church, string $eventSlug): Response
    {
        $this->ensurePublic($church);
        $event = $this->findPublicEvent($church, $eventSlug);

        return Inertia::render('public/event', [
            'church' => $this->churchPayload($church),
            'event' => $this->eventDetail($event),
        ]);
    }

    public function register(
        PublicEventRegistrationRequest $request,
        Church $church,
        string $eventSlug,
    ): RedirectResponse {
        $this->ensurePublic($church);
        $event = $this->findPublicEvent($church, $eventSlug);

        if (! $event->registrationIsOpen()) {
            return back()->with('error', 'El registro para este evento está cerrado.');
        }

        $data = $request->validated();
        $publicToken = Str::random(40);

        try {
            $registration = app(CurrentChurch::class)->run($church, function () use ($event, $data, $publicToken): EventRegistration {
                $person = $this->resolvePerson($data);

                return DB::transaction(function () use ($event, $person, $data, $publicToken): EventRegistration {
                    /** @var Event $locked */
                    $locked = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();

                    $status = RegistrationStatus::Registered->value;

                    if ($locked->isFull()) {
                        $status = RegistrationStatus::Waitlisted->value;
                    } elseif ($locked->is_paid === false) {
                        $status = RegistrationStatus::Confirmed->value;
                    }

                    return $locked->registrations()->create([
                        'person_id' => $person->getKey(),
                        'guest_name' => null,
                        'guest_count' => $data['guest_count'],
                        'status' => $status,
                        'payment_status' => $locked->is_paid
                            ? PaymentStatus::Pending->value
                            : PaymentStatus::NotRequired->value,
                        'public_token_hash' => hash('sha256', $publicToken),
                        'ticket_code_hash' => $locked->is_paid ? null : hash('sha256', $publicToken),
                        'issued_at' => $locked->is_paid ? null : now(),
                        'responded_at' => now(),
                    ]);
                });
            });
        } catch (PlanLimitExceededException) {
            return back()->with('error', 'La iglesia alcanzó su límite de personas. Contacta a la iglesia.');
        }

        DomainAudit::recordForChurch($church, DomainAudit::REGISTRATION_CREATED, $registration, [
            'eventId' => $event->getKey(),
            'personId' => $registration->person_id,
            'status' => $registration->status,
            'paymentStatus' => $registration->payment_status,
            'source' => 'public',
        ]);

        if ($event->is_paid) {
            return redirect()
                ->route('public.receipt.show', ['token' => $publicToken])
                ->with('success', 'Tu inscripción fue registrada. Sube el comprobante de pago.');
        }

        return redirect()
            ->route('public.ticket.show', ['token' => $publicToken])
            ->with('success', 'Tu inscripción fue registrada. Esta es tu entrada.');
    }

    private function ensurePublic(Church $church): void
    {
        abort_unless($church->status === 'active', 404);
    }

    private function findPublicEvent(Church $church, string $slug): Event
    {
        return app(CurrentChurch::class)->run($church, fn (): Event => Event::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where('is_public', true)
            ->firstOrFail());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePerson(array $data): Person
    {
        $person = null;

        if (! empty($data['email'])) {
            $person = Person::query()
                ->whereRaw('lower(email) = ?', [Str::lower($data['email'])])
                ->first();
        }

        if ($person === null && ! empty($data['phone'])) {
            $person = Person::query()->where('phone', $data['phone'])->first();
        }

        if ($person !== null) {
            return $person;
        }

        return Person::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => 'visitor',
            'custom_fields' => (object) [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function churchPayload(Church $church): array
    {
        $branding = $church->settings['branding'] ?? [];
        $logoPath = $branding['logo_path'] ?? null;

        return [
            'name' => $church->name,
            'slug' => $church->slug,
            'tagline' => $branding['tagline'] ?? null,
            'primaryColor' => $branding['primary_color'] ?? null,
            'accentColor' => $branding['accent_color'] ?? null,
            'serviceTimes' => $branding['service_times'] ?? null,
            'logoUrl' => $logoPath === null ? null : Storage::disk('public')->url($logoPath),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventSummary(Event $event): array
    {
        return [
            'id' => $event->getKey(),
            'title' => $event->title,
            'slug' => $event->slug,
            'startsAt' => $event->starts_at?->toIso8601String(),
            'location' => $event->location,
            'isPaid' => $event->is_paid,
            'price' => $event->price,
            'currencyCode' => $event->currency_code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventDetail(Event $event): array
    {
        $occupied = $event->occupiedSeats();

        return [
            ...$this->eventSummary($event),
            'description' => $event->description,
            'endsAt' => $event->ends_at?->toIso8601String(),
            'capacity' => $event->capacity,
            'occupied' => $occupied,
            'spotsLeft' => $event->capacity === null ? null : max(0, $event->capacity - $occupied),
            'isFull' => $event->isFull(),
            'registrationIsOpen' => $event->registrationIsOpen(),
        ];
    }
}
