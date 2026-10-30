<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonRequest;
use App\Http\Requests\UpdatePersonRequest;
use App\Models\Appointment;
use App\Models\Church;
use App\Models\CustomFieldDefinition;
use App\Models\Person;
use App\Models\PersonPipelineProgress;
use App\Models\PersonRelationship;
use App\Models\PipelineStage;
use App\Models\Segment;
use App\Support\Audit\DomainAudit;
use App\Support\Auth\Permission;
use App\Support\People\CustomFieldRegistry;
use App\Support\People\CustomFieldType;
use App\Support\People\RelationshipType;
use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanFeatures;
use App\Support\Segments\SegmentQuery;
use App\Support\Segments\SegmentType;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PersonController extends Controller
{
    public function index(Request $request, Church $church): Response
    {
        Gate::authorize('viewAny', Person::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'archived', 'visitor'])],
            'segment' => ['nullable', 'integer'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? null;
        $segmentId = $filters['segment'] ?? null;
        $segment = null;

        if ($segmentId !== null) {
            Gate::authorize('viewAny', Segment::class);
            $segment = Segment::query()->findOrFail((int) $segmentId);
        }

        $query = $segment === null
            ? Person::query()
            : ($segment->isDynamic()
                ? SegmentQuery::matchingPeople($church, is_array($segment->rules) ? $segment->rules : [])
                : Person::query()->whereIn('people.id', $segment->members()->select('person_id')));

        if ($search !== '') {
            $like = '%'.Str::lower($search).'%';

            $query->where(function ($query) use ($like): void {
                $query->whereRaw('lower(people.first_name) like ?', [$like])
                    ->orWhereRaw('lower(people.last_name) like ?', [$like])
                    ->orWhereRaw('lower(people.email) like ?', [$like])
                    ->orWhereRaw('lower(people.phone) like ?', [$like]);
            });
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        $paginator = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('people/index', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'people' => $paginator->getCollection()
                ->map(fn (Person $person): array => $this->summary($person))
                ->values(),
            'filters' => [
                'search' => $search,
                'status' => $status ?? '',
                'segment' => $segment?->getKey() !== null ? (string) $segment->getKey() : '',
            ],
            'statuses' => [
                ['value' => 'active', 'label' => 'Activas'],
                ['value' => 'inactive', 'label' => 'Inactivas'],
                ['value' => 'archived', 'label' => 'Archivadas'],
                ['value' => 'visitor', 'label' => 'Visitas'],
            ],
            'segments' => $this->segmentOptions($segment),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'previousPageUrl' => $paginator->previousPageUrl(),
                'nextPageUrl' => $paginator->nextPageUrl(),
            ],
            'canCreate' => Gate::allows('create', Person::class),
            'canArchive' => Gate::allows('archiveAny', Person::class),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function segmentOptions(?Segment $selected): array
    {
        if (! Gate::allows('viewAny', Segment::class)) {
            return [];
        }

        return Segment::query()
            ->where(function ($query) use ($selected): void {
                $query->where('is_active', true);

                if ($selected !== null && ! $selected->is_active) {
                    $query->orWhereKey($selected->getKey());
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'is_active'])
            ->map(fn (Segment $segment): array => [
                'id' => $segment->getKey(),
                'name' => $segment->name,
                'type' => $segment->type,
                'typeLabel' => SegmentType::tryFrom((string) $segment->type)?->label() ?? $segment->type,
                'isActive' => $segment->is_active,
            ])
            ->values()
            ->all();
    }

    public function create(Church $church): Response
    {
        Gate::authorize('create', Person::class);

        return Inertia::render('people/create', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'definitions' => $this->definitions(CustomFieldRegistry::activeFor($church)),
        ]);
    }

    public function store(StorePersonRequest $request, Church $church): RedirectResponse
    {
        Gate::authorize('create', Person::class);

        $data = $request->validated();
        $definitions = CustomFieldRegistry::activeFor($church);

        $person = Person::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => 'active',
            'custom_fields' => $this->customFieldsPayload(
                CustomFieldRegistry::normalize($definitions, $data['custom_fields'] ?? []),
            ),
        ]);

        DomainAudit::record(DomainAudit::PERSON_CREATED, $person, [
            'firstName' => $person->first_name,
            'lastName' => $person->last_name,
        ]);

        return redirect()
            ->route('people.show', ['church' => $church, 'person' => $person->getKey()])
            ->with('success', 'Persona creada.');
    }

    public function show(Church $church, string $person): Response
    {
        $record = $this->findPerson($person);

        Gate::authorize('view', $record);

        return Inertia::render('people/show', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'person' => $this->summary($record, detailed: true),
            'customFields' => $this->customFieldValues(
                $record,
                CustomFieldRegistry::allFor($church),
            ),
            'canUpdate' => Gate::allows('update', $record),
            'canArchive' => Gate::allows('archive', $record),
            'canScheduleAppointment' => Gate::allows('create', Appointment::class),
            'relationships' => $this->relationships($record),
            'relationshipTypes' => RelationshipType::options(),
            'relationshipPeople' => $this->relationshipPeople($record),
            'canUpdateRelationships' => Gate::allows('update', $record),
            'pipeline' => $this->canViewPipeline($church) ? $this->pipeline($record) : null,
            'canManagePipeline' => $this->canManagePipeline($church),
        ]);
    }

    public function edit(Church $church, string $person): Response
    {
        $record = $this->findPerson($person);

        Gate::authorize('update', $record);

        $definitions = CustomFieldRegistry::activeFor($church);

        return Inertia::render('people/edit', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'person' => $this->summary($record, detailed: true),
            'definitions' => $this->definitions($definitions),
            'values' => $record->custom_fields ?? [],
        ]);
    }

    public function update(
        UpdatePersonRequest $request,
        Church $church,
        string $person,
    ): RedirectResponse {
        $record = $this->findPerson($person);

        Gate::authorize('update', $record);

        $data = $request->validated();
        $definitions = CustomFieldRegistry::activeFor($church);

        $record->fill([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'custom_fields' => $this->customFieldsPayload(
                CustomFieldRegistry::normalize(
                    $definitions,
                    $data['custom_fields'] ?? [],
                    $record->custom_fields ?? [],
                ),
            ),
        ]);

        $changed = array_keys($record->getDirty());
        $record->save();

        DomainAudit::record(DomainAudit::PERSON_UPDATED, $record, [
            'changed' => $changed,
        ]);

        return redirect()
            ->route('people.show', ['church' => $church, 'person' => $record->getKey()])
            ->with('success', 'Persona actualizada.');
    }

    public function archive(Church $church, string $person): RedirectResponse
    {
        $record = $this->findPerson($person);

        Gate::authorize('archive', $record);

        if ($record->status !== 'archived') {
            $record->update(['status' => 'archived']);
            DomainAudit::record(DomainAudit::PERSON_ARCHIVED, $record, [
                'status' => 'archived',
            ]);
        }

        return back()->with('success', 'Persona archivada.');
    }

    public function restore(Church $church, string $person): RedirectResponse
    {
        $record = $this->findPerson($person);

        Gate::authorize('archive', $record);

        if ($record->status === 'archived') {
            $record->update(['status' => 'active']);
            DomainAudit::record(DomainAudit::PERSON_RESTORED, $record, [
                'status' => 'active',
            ]);
        }

        return back()->with('success', 'Persona restaurada.');
    }

    private function findPerson(string $person): Person
    {
        return Person::query()->findOrFail($person);
    }

    /**
     * An empty PHP array serializes as JSON [] but the database requires a
     * JSON object. Keep an empty object instead.
     *
     * @param  array<string, mixed>  $customFields
     */
    private function customFieldsPayload(array $customFields): array|object
    {
        return $customFields === [] ? (object) [] : $customFields;
    }

    /**
     * @param  Collection<int, CustomFieldDefinition>  $definitions
     * @return array<int, array<string, mixed>>
     */
    private function definitions(Collection $definitions): array
    {
        return $definitions->map(fn (CustomFieldDefinition $definition): array => [
            'id' => $definition->getKey(),
            'key' => $definition->key,
            'label' => $definition->label,
            'type' => $definition->type,
            'typeLabel' => CustomFieldType::tryFrom((string) $definition->type)?->label() ?? $definition->type,
            'options' => CustomFieldRegistry::optionChoices($definition->options),
            'isRequired' => $definition->is_required,
            'isActive' => $definition->is_active,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, CustomFieldDefinition>  $definitions
     * @return array<int, array<string, mixed>>
     */
    private function customFieldValues(Person $person, Collection $definitions): array
    {
        $values = $person->custom_fields ?? [];

        return $definitions
            ->filter(fn (CustomFieldDefinition $definition): bool => array_key_exists($definition->key, $values))
            ->map(fn (CustomFieldDefinition $definition): array => [
                'key' => $definition->key,
                'label' => $definition->label,
                'type' => $definition->type,
                'value' => CustomFieldRegistry::displayValue($definition, $values[$definition->key] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<int, array<string, mixed>>
     */
    private function pipeline(Person $person): array
    {
        $progress = PersonPipelineProgress::query()
            ->where('person_id', $person->getKey())
            ->where('status', 'completed')
            ->get()
            ->keyBy('pipeline_stage_id');

        return PipelineStage::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (PipelineStage $stage): array => [
                'id' => $stage->getKey(),
                'name' => $stage->name,
                'position' => $stage->position,
                'completedAt' => $progress->get($stage->getKey())?->completed_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function canViewPipeline(Church $church): bool
    {
        return app(CurrentChurch::class)->allows(Permission::PastoralView)
            && Entitlements::forChurch($church)->can(PlanFeatures::PASTORAL_PIPELINE);
    }

    private function canManagePipeline(Church $church): bool
    {
        return app(CurrentChurch::class)->allows(Permission::PastoralManage)
            && Entitlements::forChurch($church)->can(PlanFeatures::PASTORAL_PIPELINE);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relationships(Person $person): array
    {
        return PersonRelationship::query()
            ->with('relatedPerson')
            ->where('person_id', $person->getKey())
            ->orderBy('relationship')
            ->get()
            ->map(fn (PersonRelationship $relationship): array => [
                'id' => $relationship->getKey(),
                'relatedPerson' => [
                    'id' => $relationship->related_person_id,
                    'fullName' => $relationship->relatedPerson === null
                        ? null
                        : trim($relationship->relatedPerson->first_name.' '.($relationship->relatedPerson->last_name ?? '')),
                    'email' => $relationship->relatedPerson?->email,
                ],
                'relationship' => $relationship->relationship,
                'relationshipLabel' => RelationshipType::tryFrom($relationship->relationship)?->label()
                    ?? $relationship->relationship,
                'isPrimary' => $relationship->is_primary,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function relationshipPeople(Person $person): array
    {
        return Person::query()
            ->whereKeyNot($person->getKey())
            ->where('status', '!=', 'archived')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(200)
            ->get()
            ->map(fn (Person $option): array => [
                'id' => $option->getKey(),
                'fullName' => trim($option->first_name.' '.($option->last_name ?? '')),
                'email' => $option->email,
            ])
            ->values()
            ->all();
    }

    private function summary(Person $person, bool $detailed = false): array
    {
        $data = [
            'id' => $person->getKey(),
            'firstName' => $person->first_name,
            'lastName' => $person->last_name,
            'fullName' => trim($person->first_name.' '.($person->last_name ?? '')),
            'email' => $person->email,
            'phone' => $person->phone,
            'status' => $person->status,
            'createdAt' => $person->created_at?->toIso8601String(),
        ];

        if ($detailed) {
            $data['updatedAt'] = $person->updated_at?->toIso8601String();
        }

        return $data;
    }
}
