<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSegmentMemberRequest;
use App\Http\Requests\StoreSegmentRequest;
use App\Http\Requests\UpdateSegmentRequest;
use App\Models\Church;
use App\Models\Person;
use App\Models\Segment;
use App\Models\SegmentMember;
use App\Support\Audit\DomainAudit;
use App\Support\Segments\SegmentFieldCatalog;
use App\Support\Segments\SegmentQuery;
use App\Support\Segments\SegmentRuleValidator;
use App\Support\Segments\SegmentType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SegmentController extends Controller
{
    public function index(Request $request, Church $church): Response
    {
        Gate::authorize('viewAny', Segment::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
            'type' => ['nullable', 'string', Rule::in(SegmentType::values())],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $type = $filters['type'] ?? '';

        $query = Segment::query();

        if ($search !== '') {
            $like = '%'.Str::lower($search).'%';
            $query->where(function (Builder $nested) use ($like): void {
                $nested->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(description) like ?', [$like]);
            });
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        $paginator = $query
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('segments/index', [
            'church' => $this->churchPayload($church),
            'segments' => $paginator->getCollection()
                ->map(fn (Segment $segment): array => $this->summary($segment, $church))
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
                'type' => $type,
            ],
            'types' => SegmentType::options(),
            'pagination' => $this->pagination($paginator),
            'canManage' => Gate::allows('create', Segment::class),
        ]);
    }

    public function create(Church $church): Response
    {
        Gate::authorize('create', Segment::class);

        return Inertia::render('segments/create', [
            'church' => $this->churchPayload($church),
            'types' => SegmentType::options(),
            'fields' => SegmentFieldCatalog::forChurch($church),
        ]);
    }

    public function store(StoreSegmentRequest $request, Church $church): RedirectResponse
    {
        Gate::authorize('create', Segment::class);

        $data = $request->validated();

        $rules = null;

        if ($data['type'] === SegmentType::Dynamic->value) {
            $rules = SegmentRuleValidator::validate((array) ($data['rules'] ?? []), $church);
        }

        $segment = Segment::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'],
            'rules' => $rules,
            'is_active' => true,
        ]);

        DomainAudit::record(DomainAudit::SEGMENT_CREATED, $segment, [
            'name' => $segment->name,
            'type' => $segment->type,
        ]);

        return redirect()
            ->route('segments.show', ['church' => $church, 'segment' => $segment->getKey()])
            ->with('success', 'Segmento creado.');
    }

    public function show(Request $request, Church $church, string $segment): Response
    {
        Gate::authorize('viewAny', Segment::class);

        $record = $this->findSegment($church, $segment);
        Gate::authorize('view', $record);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $candidates = [];
        $candidatePagination = null;
        $pagination = null;

        if ($record->isStatic()) {
            $membersQuery = Person::query()
                ->whereIn('id', $record->members()->select('person_id'));

            $this->applySearch($membersQuery, $search);

            $membersPaginator = $membersQuery
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate(20, ['*'], 'membersPage')
                ->withQueryString();

            $candidatesQuery = Person::query()
                ->whereNotIn('id', $record->members()->select('person_id'));

            $this->applySearch($candidatesQuery, $search);

            $candidatesPaginator = $candidatesQuery
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate(10, ['*'], 'candidatesPage')
                ->withQueryString();

            $people = $membersPaginator->getCollection()
                ->map(fn (Person $person): array => $this->personSummary($person))
                ->values()
                ->all();

            $candidates = $candidatesPaginator->getCollection()
                ->map(fn (Person $person): array => $this->personSummary($person))
                ->values()
                ->all();

            $pagination = $this->pagination($membersPaginator);
            $candidatePagination = $this->pagination($candidatesPaginator);
            $memberCount = $record->members()->count();
        } else {
            $rules = is_array($record->rules) ? $record->rules : [];

            $previewQuery = SegmentQuery::matchingPeople($church, $rules);
            $this->applySearch($previewQuery, $search);

            $previewPaginator = $previewQuery
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->paginate(20, ['*'], 'previewPage')
                ->withQueryString();

            $people = $previewPaginator->getCollection()
                ->map(fn (Person $person): array => $this->personSummary($person))
                ->values()
                ->all();

            $pagination = $this->pagination($previewPaginator);
            $memberCount = SegmentQuery::count($church, $rules);
        }

        return Inertia::render('segments/show', [
            'church' => $this->churchPayload($church),
            'segment' => $this->detail($record, $church, $memberCount),
            'people' => $people,
            'candidates' => $candidates,
            'filters' => [
                'search' => $search,
            ],
            'pagination' => $pagination,
            'candidatePagination' => $candidatePagination,
            'canManage' => Gate::allows('update', $record),
        ]);
    }

    public function edit(Church $church, string $segment): Response
    {
        $record = $this->findSegment($church, $segment);
        Gate::authorize('update', $record);

        return Inertia::render('segments/edit', [
            'church' => $this->churchPayload($church),
            'segment' => [
                'id' => $record->getKey(),
                'name' => $record->name,
                'description' => $record->description,
                'type' => $record->type,
                'typeLabel' => SegmentType::tryFrom((string) $record->type)?->label() ?? $record->type,
                'rules' => $record->rules,
            ],
            'types' => SegmentType::options(),
            'fields' => SegmentFieldCatalog::forChurch($church),
        ]);
    }

    public function update(UpdateSegmentRequest $request, Church $church, string $segment): RedirectResponse
    {
        $record = $this->findSegment($church, $segment);
        Gate::authorize('update', $record);

        $data = $request->validated();
        $rules = $record->rules;

        if ($record->isDynamic()) {
            $rules = SegmentRuleValidator::validate((array) ($data['rules'] ?? []), $church);
        }

        $record->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'rules' => $rules,
        ]);

        DomainAudit::record(DomainAudit::SEGMENT_UPDATED, $record, [
            'name' => $record->name,
            'type' => $record->type,
        ]);

        return back()->with('success', 'Segmento actualizado.');
    }

    public function toggle(Church $church, string $segment): RedirectResponse
    {
        $record = $this->findSegment($church, $segment);
        Gate::authorize('update', $record);

        $record->update(['is_active' => ! $record->is_active]);

        DomainAudit::record(DomainAudit::SEGMENT_UPDATED, $record, [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ]);

        return back()->with('success', $record->is_active ? 'Segmento activado.' : 'Segmento desactivado.');
    }

    public function destroy(Church $church, string $segment): RedirectResponse
    {
        $record = $this->findSegment($church, $segment);
        Gate::authorize('delete', $record);

        DomainAudit::record(DomainAudit::SEGMENT_DELETED, $record, [
            'name' => $record->name,
            'type' => $record->type,
        ]);

        $record->delete();

        return redirect()
            ->route('segments.index', $church)
            ->with('success', 'Segmento eliminado.');
    }

    public function preview(Request $request, Church $church): JsonResponse
    {
        Gate::authorize('viewAny', Segment::class);

        $raw = (string) $request->query('rules', '');
        $rules = json_decode($raw, true);

        if (! is_array($rules)) {
            return response()->json(['message' => 'Las reglas no son válidas.'], 422);
        }

        try {
            $rules = SegmentRuleValidator::validate($rules, $church);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json(['count' => SegmentQuery::count($church, $rules)]);
    }

    public function storeMember(
        StoreSegmentMemberRequest $request,
        Church $church,
        string $segment,
    ): RedirectResponse {
        $record = $this->findSegment($church, $segment);
        Gate::authorize('update', $record);

        if (! $record->isStatic()) {
            throw ValidationException::withMessages([
                'person_id' => 'Solo puedes agregar personas a un segmento estático.',
            ]);
        }

        $data = $request->validated();

        $member = $record->members()->firstOrCreate(
            ['person_id' => $data['person_id']],
            ['added_by' => $request->user()?->getAuthIdentifier()],
        );

        if ($member->wasRecentlyCreated) {
            DomainAudit::record(DomainAudit::SEGMENT_MEMBERS_CHANGED, $record, [
                'action' => 'added',
                'personId' => $data['person_id'],
            ]);
        }

        return back()->with('success', 'Persona agregada al segmento.');
    }

    public function destroyMember(
        Church $church,
        string $segment,
        string $person,
    ): RedirectResponse {
        $record = $this->findSegment($church, $segment);
        Gate::authorize('update', $record);

        abort_unless(ctype_digit($person), 404);

        $membership = SegmentMember::query()
            ->where('segment_id', $record->getKey())
            ->where('person_id', $person)
            ->firstOrFail();

        $personId = $membership->person_id;
        $membership->delete();

        DomainAudit::record(DomainAudit::SEGMENT_MEMBERS_CHANGED, $record, [
            'action' => 'removed',
            'personId' => $personId,
        ]);

        return back()->with('success', 'Persona quitada del segmento.');
    }

    private function findSegment(Church $church, string $segment): Segment
    {
        abort_unless(ctype_digit($segment), 404);

        $record = Segment::query()->findOrFail($segment);

        abort_unless((string) $record->church_id === (string) $church->getKey(), 404);

        return $record;
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%'.Str::lower($search).'%';

        $query->where(function (Builder $nested) use ($like): void {
            $nested->whereRaw('lower(people.first_name) like ?', [$like])
                ->orWhereRaw('lower(people.last_name) like ?', [$like])
                ->orWhereRaw('lower(people.email) like ?', [$like])
                ->orWhereRaw('lower(people.phone) like ?', [$like]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Segment $segment, Church $church): array
    {
        $rules = is_array($segment->rules) ? $segment->rules : [];

        return [
            'id' => $segment->getKey(),
            'name' => $segment->name,
            'description' => $segment->description,
            'type' => $segment->type,
            'typeLabel' => SegmentType::tryFrom((string) $segment->type)?->label() ?? $segment->type,
            'isActive' => $segment->is_active,
            'memberCount' => $segment->isDynamic()
                ? SegmentQuery::count($church, $rules)
                : $segment->members()->count(),
            'rulesSummary' => $segment->isDynamic()
                ? SegmentRuleValidator::summary($rules, $church)
                : null,
            'createdAt' => $segment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Segment $segment, Church $church, int $memberCount): array
    {
        return [
            ...$this->summary($segment, $church),
            'memberCount' => $memberCount,
            'rules' => $segment->rules,
            'updatedAt' => $segment->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int|string, name: string, slug: string}
     */
    private function churchPayload(Church $church): array
    {
        return [
            'id' => $church->getKey(),
            'name' => $church->name,
            'slug' => $church->slug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function personSummary(Person $person): array
    {
        return [
            'id' => $person->getKey(),
            'firstName' => $person->first_name,
            'lastName' => $person->last_name,
            'fullName' => trim($person->first_name.' '.($person->last_name ?? '')),
            'email' => $person->email,
            'phone' => $person->phone,
            'status' => $person->status,
            'createdAt' => $person->created_at?->toIso8601String(),
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
