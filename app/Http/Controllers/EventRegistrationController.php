<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRegistrationRequest;
use App\Http\Requests\UpdateEventRegistrationStatusRequest;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Person;
use App\Support\Audit\DomainAudit;
use App\Support\Events\EventStatus;
use App\Support\Events\PaymentStatus;
use App\Support\Events\RegistrationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventRegistrationController extends Controller
{
    public function index(Request $request, Church $church, string $event): Response
    {
        $record = $this->findEvent($event);
        Gate::authorize('view', $record);

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(RegistrationStatus::values())],
        ]);

        $query = $record->registrations()->with('person');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $registrations = $query->orderByDesc('created_at')->get();

        return Inertia::render('events/registrations', [
            'church' => $this->churchPayload($church),
            'event' => $this->eventSummary($record),
            'registrations' => $registrations
                ->map(fn (EventRegistration $registration): array => $this->summary($registration))
                ->values(),
            'filters' => ['status' => $filters['status'] ?? ''],
            'statuses' => RegistrationStatus::options(),
            'people' => Person::query()
                ->where('status', '!=', 'archived')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(200)
                ->get()
                ->map(fn (Person $person): array => [
                    'id' => $person->getKey(),
                    'fullName' => trim($person->first_name.' '.($person->last_name ?? '')),
                    'email' => $person->email,
                ])
                ->values(),
            'canManage' => Gate::allows('update', $record),
        ]);
    }

    public function store(
        StoreEventRegistrationRequest $request,
        Church $church,
        string $event,
    ): RedirectResponse {
        $record = $this->findEvent($event);
        Gate::authorize('update', $record);

        if (! $record->registrationIsOpen() && $request->input('status') !== RegistrationStatus::Cancelled->value) {
            throw ValidationException::withMessages([
                'status' => 'El registro para este evento no está abierto.',
            ]);
        }

        $data = $request->validated();

        $registration = DB::transaction(function () use ($record, $data): EventRegistration {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            $status = $data['status'];

            if (RegistrationStatus::occupiesSeat($status) && $locked->isFull()) {
                $status = RegistrationStatus::Waitlisted->value;
            }

            if ($status === RegistrationStatus::Registered->value && $locked->is_paid === false) {
                $status = RegistrationStatus::Confirmed->value;
            }

            return $locked->registrations()->create([
                'person_id' => $data['person_id'] ?? null,
                'guest_name' => $data['guest_name'] ?? null,
                'guest_count' => $data['guest_count'],
                'status' => $status,
                'payment_status' => $locked->is_paid
                    ? PaymentStatus::Pending->value
                    : PaymentStatus::NotRequired->value,
                'responded_at' => now(),
            ]);
        });

        DomainAudit::record(DomainAudit::REGISTRATION_CREATED, $registration, [
            'eventId' => $record->getKey(),
            'personId' => $registration->person_id,
            'status' => $registration->status,
            'paymentStatus' => $registration->payment_status,
        ]);

        return back()->with('success', 'Inscripción registrada.');
    }

    public function updateStatus(
        UpdateEventRegistrationStatusRequest $request,
        Church $church,
        string $event,
        string $registration,
    ): RedirectResponse {
        $record = $this->findEvent($event);
        Gate::authorize('update', $record);

        $entry = $record->registrations()->findOrFail($registration);
        $data = $request->validated();

        DB::transaction(function () use ($record, $entry, $data): void {
            /** @var Event $locked */
            $locked = Event::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            if (
                RegistrationStatus::occupiesSeat($data['status'])
                && ! RegistrationStatus::occupiesSeat($entry->status)
                && $locked->isFull()
            ) {
                throw ValidationException::withMessages([
                    'status' => 'El evento alcanzó su cupo máximo.',
                ]);
            }

            $entry->forceFill([
                'status' => $data['status'],
                'responded_at' => now(),
            ])->save();
        });

        DomainAudit::record(DomainAudit::REGISTRATION_STATUS_CHANGED, $entry, [
            'eventId' => $record->getKey(),
            'status' => $entry->status,
        ]);

        return back()->with('success', 'Estado de la inscripción actualizado.');
    }

    public function destroy(Church $church, string $event, string $registration): RedirectResponse
    {
        $record = $this->findEvent($event);
        Gate::authorize('update', $record);

        $entry = $record->registrations()->findOrFail($registration);
        $entry->forceFill([
            'status' => RegistrationStatus::Cancelled->value,
            'responded_at' => now(),
        ])->save();

        DomainAudit::record(DomainAudit::REGISTRATION_STATUS_CHANGED, $entry, [
            'eventId' => $record->getKey(),
            'status' => $entry->status,
        ]);

        return back()->with('success', 'Inscripción cancelada.');
    }

    private function findEvent(string $event): Event
    {
        return Event::query()->findOrFail($event);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(EventRegistration $registration): array
    {
        return [
            'id' => $registration->getKey(),
            'person' => $registration->person === null ? null : [
                'id' => $registration->person->getKey(),
                'fullName' => trim($registration->person->first_name.' '.($registration->person->last_name ?? '')),
                'email' => $registration->person->email,
            ],
            'guestName' => $registration->guest_name,
            'guestCount' => $registration->guest_count,
            'status' => $registration->status,
            'statusLabel' => RegistrationStatus::label($registration->status),
            'paymentStatus' => $registration->payment_status,
            'paymentStatusLabel' => PaymentStatus::label($registration->payment_status),
            'respondedAt' => $registration->responded_at?->toIso8601String(),
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
            'status' => $event->status,
            'statusLabel' => EventStatus::label($event->status),
            'capacity' => $event->capacity,
            'occupied' => $event->occupiedSeats(),
            'isPaid' => $event->is_paid,
            'price' => $event->price,
            'currencyCode' => $event->currency_code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function churchPayload(Church $church): array
    {
        return [
            'name' => $church->name,
            'slug' => $church->slug,
        ];
    }
}
