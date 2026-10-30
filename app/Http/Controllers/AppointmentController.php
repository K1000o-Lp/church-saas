<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\RescheduleAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Person;
use App\Support\Appointments\AppointmentStatus;
use App\Support\Audit\DomainAudit;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request, Church $church): Response
    {
        Gate::authorize('viewAny', Appointment::class);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', Rule::in(AppointmentStatus::values())],
            'host' => ['nullable', 'integer'],
        ]);

        $query = Appointment::query()->with(['person', 'host']);

        if (! $this->isManager()) {
            $query->where('host_user_id', $this->currentUserId());
        }

        if (! empty($filters['from'])) {
            $query->where('scheduled_at', '>=', $filters['from'].' 00:00:00');
        }

        if (! empty($filters['to'])) {
            $query->where('scheduled_at', '<=', $filters['to'].' 23:59:59');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['host']) && $this->isManager()) {
            $query->where('host_user_id', $filters['host']);
        }

        $paginator = $query
            ->orderBy('scheduled_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('appointments/index', [
            'church' => $this->churchPayload($church),
            'appointments' => $paginator->getCollection()
                ->map(fn (Appointment $appointment): array => $this->summary($appointment))
                ->values(),
            'filters' => [
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'status' => $filters['status'] ?? '',
                'host' => isset($filters['host']) ? (string) $filters['host'] : '',
            ],
            'statuses' => AppointmentStatus::options(),
            'hosts' => $this->hostOptions($church),
            'canManage' => Gate::allows('create', Appointment::class),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function create(Request $request, Church $church): Response
    {
        Gate::authorize('create', Appointment::class);

        $selectedPersonId = $request->integer('person');

        return Inertia::render('appointments/create', [
            'church' => $this->churchPayload($church),
            'people' => $this->peopleOptions(),
            'hosts' => $this->hostOptions($church),
            'selectedPersonId' => $selectedPersonId > 0 ? $selectedPersonId : null,
        ]);
    }

    public function store(StoreAppointmentRequest $request, Church $church): RedirectResponse
    {
        Gate::authorize('create', Appointment::class);

        $data = $request->validated();

        $appointment = Appointment::create([
            'person_id' => $data['person_id'],
            'host_user_id' => $data['host_user_id'],
            'created_by' => $this->currentUserId(),
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'],
            'status' => AppointmentStatus::Scheduled->value,
            'subject' => $data['subject'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        DomainAudit::record(DomainAudit::APPOINTMENT_CREATED, $appointment, [
            'personId' => $appointment->person_id,
            'hostUserId' => $appointment->host_user_id,
            'scheduledAt' => $appointment->scheduled_at?->toIso8601String(),
            'status' => $appointment->status,
        ]);

        return redirect()
            ->route('appointments.show', ['church' => $church, 'appointment' => $appointment->getKey()])
            ->with('success', 'Cita programada.');
    }

    public function show(Church $church, string $appointment): Response
    {
        $record = $this->findAppointment($appointment);

        Gate::authorize('view', $record);

        $canViewNotes = Gate::allows('viewNotes', $record);

        return Inertia::render('appointments/show', [
            'church' => $this->churchPayload($church),
            'appointment' => $this->detail($record, $canViewNotes),
            'canManage' => Gate::allows('update', $record),
            'canViewNotes' => $canViewNotes,
        ]);
    }

    public function edit(Church $church, string $appointment): Response
    {
        $record = $this->findAppointment($appointment);

        Gate::authorize('update', $record);

        return Inertia::render('appointments/edit', [
            'church' => $this->churchPayload($church),
            'appointment' => $this->detail($record, Gate::allows('viewNotes', $record)),
            'people' => $this->peopleOptions(),
            'hosts' => $this->hostOptions($church),
        ]);
    }

    public function update(UpdateAppointmentRequest $request, Church $church, string $appointment): RedirectResponse
    {
        $record = $this->findAppointment($appointment);

        Gate::authorize('update', $record);

        $data = $request->validated();

        $record->fill([
            'person_id' => $data['person_id'],
            'host_user_id' => $data['host_user_id'],
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'],
            'subject' => $data['subject'] ?? null,
            'notes' => $data['notes'] ?? null,
        ])->save();

        DomainAudit::record(DomainAudit::APPOINTMENT_UPDATED, $record, [
            'personId' => $record->person_id,
            'hostUserId' => $record->host_user_id,
            'scheduledAt' => $record->scheduled_at?->toIso8601String(),
        ]);

        return redirect()
            ->route('appointments.show', ['church' => $church, 'appointment' => $record->getKey()])
            ->with('success', 'Cita actualizada.');
    }

    public function complete(Church $church, string $appointment): RedirectResponse
    {
        $record = $this->findAppointment($appointment);

        Gate::authorize('complete', $record);
        $this->ensureOpen($record);

        $record->forceFill([
            'status' => AppointmentStatus::Completed->value,
            'completed_at' => now(),
        ])->save();

        DomainAudit::record(DomainAudit::APPOINTMENT_COMPLETED, $record, [
            'scheduledAt' => $record->scheduled_at?->toIso8601String(),
        ]);

        return back()->with('success', 'Cita marcada como completada.');
    }

    public function cancel(CancelAppointmentRequest $request, Church $church, string $appointment): RedirectResponse
    {
        $record = $this->findAppointment($appointment);

        Gate::authorize('cancel', $record);
        $this->ensureOpen($record);

        $data = $request->validated();

        $record->forceFill([
            'status' => AppointmentStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancellation_reason' => $data['reason'] ?? null,
        ])->save();

        DomainAudit::record(DomainAudit::APPOINTMENT_CANCELLED, $record, [
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Cita cancelada.');
    }

    public function reschedule(
        RescheduleAppointmentRequest $request,
        Church $church,
        string $appointment,
    ): RedirectResponse {
        $record = $this->findAppointment($appointment);

        Gate::authorize('reschedule', $record);
        $this->ensureOpen($record);

        $data = $request->validated();
        $previous = $record->scheduled_at?->toIso8601String();

        $record->forceFill([
            'scheduled_at' => $data['scheduled_at'],
            'duration_minutes' => $data['duration_minutes'],
            'status' => AppointmentStatus::Rescheduled->value,
        ])->save();

        DomainAudit::record(DomainAudit::APPOINTMENT_RESCHEDULED, $record, [
            'previousScheduledAt' => $previous,
            'scheduledAt' => $record->scheduled_at?->toIso8601String(),
        ]);

        return back()->with('success', 'Cita reagendada.');
    }

    public function noShow(Church $church, string $appointment): RedirectResponse
    {
        $record = $this->findAppointment($appointment);

        Gate::authorize('noShow', $record);
        $this->ensureOpen($record);

        $record->forceFill([
            'status' => AppointmentStatus::NoShow->value,
        ])->save();

        DomainAudit::record(DomainAudit::APPOINTMENT_NO_SHOW, $record, [
            'scheduledAt' => $record->scheduled_at?->toIso8601String(),
        ]);

        return back()->with('success', 'Cita marcada como no asistida.');
    }

    private function findAppointment(string $appointment): Appointment
    {
        return Appointment::query()->findOrFail($appointment);
    }

    private function ensureOpen(Appointment $appointment): void
    {
        if (! AppointmentStatus::isOpen($appointment->status)) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden modificar citas programadas o reagendadas.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Appointment $appointment): array
    {
        return [
            'id' => $appointment->getKey(),
            'person' => $this->personSummary($appointment->person),
            'host' => [
                'id' => $appointment->host_user_id,
                'name' => $appointment->host?->name,
            ],
            'scheduledAt' => $appointment->scheduled_at?->toIso8601String(),
            'durationMinutes' => $appointment->duration_minutes,
            'status' => $appointment->status,
            'statusLabel' => AppointmentStatus::label($appointment->status),
            'subject' => $appointment->subject,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Appointment $appointment, bool $includeNotes): array
    {
        return [
            ...$this->summary($appointment),
            'notes' => $includeNotes ? $appointment->notes : null,
            'cancellationReason' => $appointment->cancellation_reason,
            'completedAt' => $appointment->completed_at?->toIso8601String(),
            'cancelledAt' => $appointment->cancelled_at?->toIso8601String(),
            'createdAt' => $appointment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personSummary(?Person $person): ?array
    {
        if ($person === null) {
            return null;
        }

        return [
            'id' => $person->getKey(),
            'fullName' => trim($person->first_name.' '.($person->last_name ?? '')),
            'email' => $person->email,
            'phone' => $person->phone,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function peopleOptions(): array
    {
        return Person::query()
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
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hostOptions(Church $church): array
    {
        $query = ChurchMembership::query()
            ->with('user')
            ->where('church_id', $church->getKey())
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'pastor', 'leader']);

        if (! $this->isManager()) {
            $query->where('user_id', $this->currentUserId());
        }

        return $query
            ->orderBy('role')
            ->get()
            ->map(fn (ChurchMembership $membership): array => [
                'id' => $membership->user_id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'role' => $membership->role,
            ])
            ->values()
            ->all();
    }

    private function isManager(): bool
    {
        return in_array(app(CurrentChurch::class)->role(), ['admin', 'pastor'], true);
    }

    private function currentUserId(): ?int
    {
        $userId = app(CurrentChurch::class)->membership()?->user_id;

        return $userId === null ? null : (int) $userId;
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

    /**
     * @return array<string, mixed>
     */
    private function pagination(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'previousPageUrl' => $paginator->previousPageUrl(),
            'nextPageUrl' => $paginator->nextPageUrl(),
        ];
    }
}
