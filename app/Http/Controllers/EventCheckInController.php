<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\Event;
use App\Support\Audit\DomainAudit;
use App\Support\Events\EventStatus;
use App\Support\Events\PaymentStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventCheckInController extends Controller
{
    public function index(Church $church, string $event): Response
    {
        $record = $this->findEvent($event);
        Gate::authorize('view', $record);

        $checkedIn = $record->attendanceRecords()
            ->with('person')
            ->where('status', 'present')
            ->orderByDesc('recorded_at')
            ->limit(20)
            ->get();

        return Inertia::render('events/check-in', [
            'church' => $this->churchPayload($church),
            'event' => [
                'id' => $record->getKey(),
                'title' => $record->title,
                'status' => $record->status,
                'statusLabel' => EventStatus::label($record->status),
                'isPaid' => $record->is_paid,
            ],
            'recentCheckIns' => $checkedIn->map(fn (AttendanceRecord $attendance): array => [
                'id' => $attendance->getKey(),
                'personName' => $attendance->person === null
                    ? null
                    : trim($attendance->person->first_name.' '.($attendance->person->last_name ?? '')),
                'recordedAt' => $attendance->recorded_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function store(Request $request, Church $church, string $event): RedirectResponse
    {
        $record = $this->findEvent($event);
        Gate::authorize('view', $record);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:2048'],
        ]);

        $code = $this->normalizeCode($data['code']);

        $registration = $record->registrations()
            ->where('ticket_code_hash', hash('sha256', $code))
            ->first();

        if ($registration === null || $registration->issued_at === null) {
            return back()->with('error', 'Entrada no válida para este evento.');
        }

        if (
            $record->is_paid
            && ! in_array($registration->payment_status, [
                PaymentStatus::Paid->value,
                PaymentStatus::NotRequired->value,
            ], true)
        ) {
            return back()->with('error', 'La entrada todavía no tiene el pago aprobado.');
        }

        $userId = app(CurrentChurch::class)->membership()?->user_id;

        $attendance = AttendanceRecord::query()->updateOrCreate(
            [
                'event_id' => $record->getKey(),
                'event_registration_id' => $registration->getKey(),
            ],
            [
                'person_id' => $registration->person_id,
                'status' => 'present',
                'source' => 'device',
                'recorded_at' => now(),
                'recorded_by' => $userId,
            ],
        );

        DomainAudit::record(DomainAudit::ATTENDANCE_RECORDED, $attendance, [
            'eventId' => $record->getKey(),
            'registrationId' => $registration->getKey(),
            'source' => 'device',
        ]);

        $name = $registration->person === null
            ? ($registration->guest_name ?? 'Invitado')
            : trim($registration->person->first_name.' '.($registration->person->last_name ?? ''));

        return back()->with('success', 'Check-in registrado para '.$name.'.');
    }

    private function normalizeCode(string $code): string
    {
        $code = trim($code);

        if (str_contains($code, '/')) {
            $path = parse_url($code, PHP_URL_PATH);

            if (is_string($path)) {
                $parts = array_values(array_filter(explode('/', $path)));

                return (string) end($parts);
            }
        }

        return $code;
    }

    private function findEvent(string $event): Event
    {
        return Event::query()->findOrFail($event);
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
