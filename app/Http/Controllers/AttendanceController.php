<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Support\Audit\DomainAudit;
use App\Support\Auth\Permission;
use App\Support\Events\EventStatus;
use App\Support\Events\RegistrationStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Church $church, string $event): Response
    {
        $record = $this->findEvent($event);
        Gate::authorize('view', $record);

        $registrations = $record->registrations()
            ->with(['person', 'attendanceRecords'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('events/attendance', [
            'church' => $this->churchPayload($church),
            'event' => [
                'id' => $record->getKey(),
                'title' => $record->title,
                'status' => $record->status,
                'statusLabel' => EventStatus::label($record->status),
                'capacity' => $record->capacity,
                'occupied' => $record->occupiedSeats(),
            ],
            'registrations' => $registrations
                ->map(fn (EventRegistration $registration): array => [
                    'id' => $registration->getKey(),
                    'person' => $registration->person === null ? null : [
                        'id' => $registration->person->getKey(),
                        'fullName' => trim($registration->person->first_name.' '.($registration->person->last_name ?? '')),
                    ],
                    'guestName' => $registration->guest_name,
                    'guestCount' => $registration->guest_count,
                    'status' => $registration->status,
                    'statusLabel' => RegistrationStatus::label($registration->status),
                    'attendanceStatus' => $registration->attendanceRecords->first()?->status,
                    'attendanceStatusLabel' => $this->attendanceLabel(
                        $registration->attendanceRecords->first()?->status,
                    ),
                ])
                ->values(),
            'attendanceStatuses' => [
                ['value' => 'present', 'label' => 'Presente'],
                ['value' => 'absent', 'label' => 'Ausente'],
                ['value' => 'late', 'label' => 'Tarde'],
                ['value' => 'excused', 'label' => 'Justificado'],
            ],
            'canRecord' => Gate::allows('view', $record)
                && app(CurrentChurch::class)->allows(Permission::AttendanceRecord),
        ]);
    }

    public function store(
        StoreAttendanceRequest $request,
        Church $church,
        string $event,
    ): RedirectResponse {
        $record = $this->findEvent($event);
        Gate::authorize('view', $record);

        $data = $request->validated();
        $registration = null;

        if (! empty($data['event_registration_id'])) {
            $registration = $record->registrations()->findOrFail($data['event_registration_id']);

            if (($data['person_id'] ?? null) !== null && (int) $data['person_id'] !== (int) $registration->person_id) {
                return back()->withErrors([
                    'person_id' => 'La persona no coincide con la inscripción.',
                ]);
            }
        }

        $currentChurch = app(CurrentChurch::class);
        $userId = $currentChurch->membership()?->user_id;

        $keys = [
            'event_id' => $record->getKey(),
            'event_registration_id' => $registration?->getKey(),
            'person_id' => $registration?->person_id ?? $data['person_id'] ?? null,
        ];

        $attendance = AttendanceRecord::query()->updateOrCreate($keys, [
            'status' => $data['status'],
            'source' => $data['source'] ?? 'manual',
            'recorded_at' => now(),
            'recorded_by' => $userId,
        ]);

        DomainAudit::record(DomainAudit::ATTENDANCE_RECORDED, $attendance, [
            'eventId' => $record->getKey(),
            'registrationId' => $attendance->event_registration_id,
            'personId' => $attendance->person_id,
            'status' => $attendance->status,
        ]);

        return back()->with('success', 'Asistencia registrada.');
    }

    private function findEvent(string $event): Event
    {
        return Event::query()->findOrFail($event);
    }

    private function attendanceLabel(?string $status): ?string
    {
        return match ($status) {
            'present' => 'Presente',
            'absent' => 'Ausente',
            'late' => 'Tarde',
            'excused' => 'Justificado',
            default => null,
        };
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
