<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServicePlanRequest;
use App\Http\Requests\UpdateServicePlanRequest;
use App\Models\Church;
use App\Models\MinistryTeam;
use App\Models\Person;
use App\Models\ServiceItem;
use App\Models\ServicePlan;
use App\Models\VolunteerAssignment;
use App\Support\Audit\DomainAudit;
use App\Support\Auth\Permission;
use App\Support\Services\AssignmentStatus;
use App\Support\Services\ServiceStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ServicePlanController extends Controller
{
    public function index(Request $request, Church $church): Response
    {
        Gate::authorize('viewAny', ServicePlan::class);

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(ServiceStatus::values())],
        ]);

        $query = ServicePlan::query()->withCount('items');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $paginator = $query
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('services/index', [
            'church' => $this->churchPayload($church),
            'plans' => $paginator->getCollection()
                ->map(fn (ServicePlan $plan): array => $this->summary($plan))
                ->values(),
            'filters' => ['status' => $filters['status'] ?? ''],
            'statuses' => ServiceStatus::options(),
            'canManage' => app(CurrentChurch::class)->allows(Permission::TeamsManage),
            'pagination' => $this->pagination($paginator),
        ]);
    }

    public function create(Church $church): Response
    {
        return Inertia::render('services/create', [
            'church' => $this->churchPayload($church),
            'statuses' => $this->editableStatuses(),
        ]);
    }

    public function store(StoreServicePlanRequest $request, Church $church): RedirectResponse
    {
        $data = $request->validated();

        $plan = ServicePlan::create($this->payload($data));

        DomainAudit::record(DomainAudit::SERVICE_CREATED, $plan, [
            'title' => $plan->title,
            'status' => $plan->status,
            'startsAt' => $plan->starts_at?->toIso8601String(),
        ]);

        return redirect()
            ->route('services.show', ['church' => $church, 'service' => $plan->getKey()])
            ->with('success', 'Culto creado.');
    }

    public function show(Church $church, string $service): Response
    {
        $record = $this->findPlan($service);
        Gate::authorize('view', $record);

        return Inertia::render('services/show', [
            'church' => $this->churchPayload($church),
            'plan' => $this->detail($record),
            'items' => $record->items()
                ->with(['ministryTeam', 'volunteerAssignments.person'])
                ->orderBy('position')
                ->get()
                ->map(fn (ServiceItem $item): array => $this->itemDetail($item))
                ->values(),
            'teams' => MinistryTeam::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (MinistryTeam $team): array => [
                    'id' => $team->getKey(),
                    'name' => $team->name,
                ])
                ->values(),
            'people' => Person::query()
                ->where('status', '!=', 'archived')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(200)
                ->get()
                ->map(fn (Person $person): array => [
                    'id' => $person->getKey(),
                    'fullName' => trim($person->first_name.' '.($person->last_name ?? '')),
                ])
                ->values(),
            'assignmentStatuses' => AssignmentStatus::options(),
            'statuses' => ServiceStatus::options(),
            'canManage' => app(CurrentChurch::class)->allows(Permission::TeamsManage),
            'canManageVolunteers' => app(CurrentChurch::class)->allows(Permission::VolunteersManage),
        ]);
    }

    public function edit(Church $church, string $service): Response
    {
        $record = $this->findPlan($service);
        Gate::authorize('update', $record);

        return Inertia::render('services/edit', [
            'church' => $this->churchPayload($church),
            'plan' => $this->detail($record),
            'statuses' => $this->editableStatuses(),
        ]);
    }

    public function update(
        UpdateServicePlanRequest $request,
        Church $church,
        string $service,
    ): RedirectResponse {
        $record = $this->findPlan($service);
        Gate::authorize('update', $record);

        $data = $request->validated();
        $record->fill($this->payload($data))->save();

        DomainAudit::record(DomainAudit::SERVICE_UPDATED, $record, [
            'title' => $record->title,
            'status' => $record->status,
            'startsAt' => $record->starts_at?->toIso8601String(),
        ]);

        return redirect()
            ->route('services.show', ['church' => $church, 'service' => $record->getKey()])
            ->with('success', 'Culto actualizado.');
    }

    public function publish(Church $church, string $service): RedirectResponse
    {
        return $this->changeStatus($service, ServiceStatus::Published, DomainAudit::SERVICE_PUBLISHED);
    }

    public function cancel(Church $church, string $service): RedirectResponse
    {
        return $this->changeStatus($service, ServiceStatus::Cancelled, DomainAudit::SERVICE_CANCELLED);
    }

    public function complete(Church $church, string $service): RedirectResponse
    {
        return $this->changeStatus($service, ServiceStatus::Completed, DomainAudit::SERVICE_COMPLETED);
    }

    private function changeStatus(string $service, ServiceStatus $status, string $action): RedirectResponse
    {
        $record = $this->findPlan($service);
        Gate::authorize('update', $record);

        if ($status === ServiceStatus::Published && $record->starts_at === null) {
            throw ValidationException::withMessages([
                'starts_at' => 'Define la fecha de inicio antes de publicar el culto.',
            ]);
        }

        $record->forceFill(['status' => $status->value])->save();

        DomainAudit::record($action, $record, [
            'status' => $record->status,
        ]);

        return back()->with('success', 'Estado del culto actualizado.');
    }

    private function findPlan(string $service): ServicePlan
    {
        return ServicePlan::query()->findOrFail($service);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        return [
            'title' => $data['title'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'location' => $data['location'] ?? null,
            'status' => $data['status'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ServicePlan $plan): array
    {
        return [
            'id' => $plan->getKey(),
            'title' => $plan->title,
            'startsAt' => $plan->starts_at?->toIso8601String(),
            'endsAt' => $plan->ends_at?->toIso8601String(),
            'location' => $plan->location,
            'status' => $plan->status,
            'statusLabel' => ServiceStatus::label($plan->status),
            'itemsCount' => (int) ($plan->items_count ?? $plan->items()->count()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(ServicePlan $plan): array
    {
        return $this->summary($plan);
    }

    /**
     * @return array<string, mixed>
     */
    private function itemDetail(ServiceItem $item): array
    {
        return [
            'id' => $item->getKey(),
            'title' => $item->title,
            'position' => $item->position,
            'notes' => $item->notes,
            'team' => $item->ministryTeam === null ? null : [
                'id' => $item->ministryTeam->getKey(),
                'name' => $item->ministryTeam->name,
            ],
            'assignments' => $item->volunteerAssignments
                ->map(fn (VolunteerAssignment $assignment): array => [
                    'id' => $assignment->getKey(),
                    'person' => $assignment->person === null ? null : [
                        'id' => $assignment->person->getKey(),
                        'fullName' => trim($assignment->person->first_name.' '.($assignment->person->last_name ?? '')),
                    ],
                    'role' => $assignment->role,
                    'status' => $assignment->status,
                    'statusLabel' => AssignmentStatus::label($assignment->status),
                ])
                ->values(),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function editableStatuses(): array
    {
        return array_values(array_filter(
            ServiceStatus::options(),
            fn (array $option): bool => in_array($option['value'], [
                ServiceStatus::Draft->value,
                ServiceStatus::Published->value,
            ], true),
        ));
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
