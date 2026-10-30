<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\Event;
use App\Support\Audit\DomainAudit;
use App\Support\Events\EventStatus;
use App\Support\Events\RegistrationStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request, Church $church): Response
    {
        Gate::authorize('viewAny', Event::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', Rule::in(EventStatus::values())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));

        $query = Event::query()
            ->withCount([
                'registrations',
                'registrations as occupied_count' => fn ($query) => $query->whereIn('status', [
                    RegistrationStatus::Registered->value,
                    RegistrationStatus::Confirmed->value,
                ]),
            ]);

        if ($search !== '') {
            $like = '%'.Str::lower($search).'%';
            $query->where(function ($query) use ($like): void {
                $query->whereRaw('lower(title) like ?', [$like])
                    ->orWhereRaw('lower(location) like ?', [$like]);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from'])) {
            $query->where('starts_at', '>=', $filters['from'].' 00:00:00');
        }

        if (! empty($filters['to'])) {
            $query->where('starts_at', '<=', $filters['to'].' 23:59:59');
        }

        $paginator = $query
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('events/index', [
            'church' => $this->churchPayload($church),
            'events' => $paginator->getCollection()
                ->map(fn (Event $event): array => $this->summary($event))
                ->values(),
            'filters' => [
                'search' => $search,
                'status' => $filters['status'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'statuses' => EventStatus::options(),
            'canManage' => Gate::allows('create', Event::class),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function create(Church $church): Response
    {
        Gate::authorize('create', Event::class);

        return Inertia::render('events/create', [
            'church' => $this->churchPayload($church),
            'statuses' => $this->statusOptions(),
            'currencies' => $this->currencyOptions($church),
        ]);
    }

    public function store(StoreEventRequest $request, Church $church): RedirectResponse
    {
        Gate::authorize('create', Event::class);

        $data = $request->validated();

        $event = Event::create($this->payload($data, $church) + [
            'slug' => $this->uniqueSlug($church, $data['title']),
        ]);

        DomainAudit::record(DomainAudit::EVENT_CREATED, $event, [
            'title' => $event->title,
            'status' => $event->status,
            'startsAt' => $event->starts_at?->toIso8601String(),
            'isPaid' => $event->is_paid,
        ]);

        return redirect()
            ->route('events.show', ['church' => $church, 'event' => $event->getKey()])
            ->with('success', 'Evento creado.');
    }

    public function show(Church $church, string $event): Response
    {
        $record = $this->findEvent($event);

        Gate::authorize('view', $record);

        return Inertia::render('events/show', [
            'church' => $this->churchPayload($church),
            'event' => $this->detail($record),
            'canManage' => Gate::allows('update', $record),
        ]);
    }

    public function edit(Church $church, string $event): Response
    {
        $record = $this->findEvent($event);

        Gate::authorize('update', $record);

        return Inertia::render('events/edit', [
            'church' => $this->churchPayload($church),
            'event' => $this->detail($record),
            'statuses' => $this->statusOptions(),
            'currencies' => $this->currencyOptions($church),
        ]);
    }

    public function update(UpdateEventRequest $request, Church $church, string $event): RedirectResponse
    {
        $record = $this->findEvent($event);

        Gate::authorize('update', $record);

        $data = $request->validated();

        $record->fill($this->payload($data, $church))->save();

        DomainAudit::record(DomainAudit::EVENT_UPDATED, $record, [
            'title' => $record->title,
            'status' => $record->status,
            'startsAt' => $record->starts_at?->toIso8601String(),
            'isPaid' => $record->is_paid,
        ]);

        return redirect()
            ->route('events.show', ['church' => $church, 'event' => $record->getKey()])
            ->with('success', 'Evento actualizado.');
    }

    public function publish(Church $church, string $event): RedirectResponse
    {
        $record = $this->findEvent($event);

        Gate::authorize('publish', $record);

        if ($record->starts_at === null) {
            throw ValidationException::withMessages([
                'starts_at' => 'Define la fecha de inicio antes de publicar el evento.',
            ]);
        }

        $record->forceFill(['status' => EventStatus::Published->value])->save();

        DomainAudit::record(DomainAudit::EVENT_PUBLISHED, $record, [
            'startsAt' => $record->starts_at?->toIso8601String(),
        ]);

        return back()->with('success', 'Evento publicado.');
    }

    public function cancel(Church $church, string $event): RedirectResponse
    {
        $record = $this->findEvent($event);

        Gate::authorize('cancel', $record);

        $record->forceFill(['status' => EventStatus::Cancelled->value])->save();

        DomainAudit::record(DomainAudit::EVENT_CANCELLED, $record, [
            'startsAt' => $record->starts_at?->toIso8601String(),
        ]);

        return back()->with('success', 'Evento cancelado.');
    }

    public function complete(Church $church, string $event): RedirectResponse
    {
        $record = $this->findEvent($event);

        Gate::authorize('complete', $record);

        $record->forceFill(['status' => EventStatus::Completed->value])->save();

        DomainAudit::record(DomainAudit::EVENT_COMPLETED, $record, [
            'startsAt' => $record->starts_at?->toIso8601String(),
        ]);

        return back()->with('success', 'Evento marcado como completado.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data, Church $church): array
    {
        $isPaid = (bool) ($data['is_paid'] ?? false);

        return [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'location' => $data['location'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'timezone' => $data['timezone'] ?? $church->timezone,
            'status' => $data['status'],
            'capacity' => $data['capacity'] ?? null,
            'is_public' => (bool) ($data['is_public'] ?? false),
            'is_paid' => $isPaid,
            'price' => $isPaid ? $data['price'] : null,
            'currency_code' => $isPaid ? $data['currency_code'] : null,
            'registration_opens_at' => $data['registration_opens_at'] ?? null,
            'registration_closes_at' => $data['registration_closes_at'] ?? null,
        ];
    }

    private function findEvent(string $event): Event
    {
        return Event::query()->findOrFail($event);
    }

    private function uniqueSlug(Church $church, string $title): string
    {
        $base = Str::slug($title) ?: 'evento';
        $slug = $base;
        $suffix = 2;

        while (Event::query()->where('church_id', $church->getKey())->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Event $event): array
    {
        return [
            'id' => $event->getKey(),
            'title' => $event->title,
            'slug' => $event->slug,
            'status' => $event->status,
            'statusLabel' => EventStatus::label($event->status),
            'startsAt' => $event->starts_at?->toIso8601String(),
            'endsAt' => $event->ends_at?->toIso8601String(),
            'location' => $event->location,
            'capacity' => $event->capacity,
            'occupied' => (int) ($event->occupied_count ?? $event->occupiedSeats()),
            'registrationsCount' => (int) ($event->registrations_count ?? $event->registrations()->count()),
            'isPublic' => $event->is_public,
            'isPaid' => $event->is_paid,
            'price' => $event->price,
            'currencyCode' => $event->currency_code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Event $event): array
    {
        return [
            ...$this->summary($event),
            'description' => $event->description,
            'timezone' => $event->timezone,
            'registrationOpensAt' => $event->registration_opens_at?->toIso8601String(),
            'registrationClosesAt' => $event->registration_closes_at?->toIso8601String(),
            'isFull' => $event->isFull(),
            'registrationIsOpen' => $event->registrationIsOpen(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_values(array_filter(
            EventStatus::options(),
            fn (array $option): bool => in_array($option['value'], [
                EventStatus::Draft->value,
                EventStatus::Published->value,
            ], true),
        ));
    }

    /**
     * @return array<int, array{code: string, isDefault: bool}>
     */
    private function currencyOptions(Church $church): array
    {
        return ChurchCurrency::query()
            ->where('church_id', $church->getKey())
            ->orderByDesc('is_default')
            ->orderBy('currency_code')
            ->get()
            ->map(fn (ChurchCurrency $currency): array => [
                'code' => $currency->currency_code,
                'isDefault' => $currency->is_default,
            ])
            ->values()
            ->all();
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
