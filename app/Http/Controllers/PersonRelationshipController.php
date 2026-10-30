<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePersonRelationshipRequest;
use App\Models\Church;
use App\Models\Person;
use App\Models\PersonRelationship;
use App\Support\Audit\DomainAudit;
use App\Support\People\RelationshipType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PersonRelationshipController extends Controller
{
    public function store(
        StorePersonRelationshipRequest $request,
        Church $church,
        string $person,
    ): RedirectResponse {
        $record = $this->findPerson($person);
        Gate::authorize('update', $record);

        $data = $request->validated();
        $type = RelationshipType::from($data['relationship']);
        $inverse = $type->inverse();

        DB::transaction(function () use ($record, $data, $type, $inverse): void {
            PersonRelationship::query()->firstOrCreate(
                [
                    'person_id' => $record->getKey(),
                    'related_person_id' => $data['related_person_id'],
                    'relationship' => $type->value,
                ],
                ['is_primary' => (bool) ($data['is_primary'] ?? false)],
            );

            if ($inverse !== null) {
                PersonRelationship::query()->firstOrCreate([
                    'person_id' => $data['related_person_id'],
                    'related_person_id' => $record->getKey(),
                    'relationship' => $inverse->value,
                ]);
            }
        });

        DomainAudit::record(DomainAudit::PERSON_RELATIONSHIP_ADDED, $record, [
            'relatedPersonId' => $data['related_person_id'],
            'relationship' => $type->value,
            'reciprocal' => $inverse?->value,
        ]);

        return back()->with('success', 'Relación familiar agregada.');
    }

    public function destroy(
        Church $church,
        string $person,
        string $relationship,
    ): RedirectResponse {
        $record = $this->findPerson($person);
        Gate::authorize('update', $record);

        $entry = PersonRelationship::query()
            ->whereKey($relationship)
            ->where('person_id', $record->getKey())
            ->firstOrFail();

        $inverse = RelationshipType::tryFrom((string) $entry->relationship)?->inverse();

        DB::transaction(function () use ($entry, $inverse): void {
            if ($inverse !== null) {
                PersonRelationship::query()
                    ->where('person_id', $entry->related_person_id)
                    ->where('related_person_id', $entry->person_id)
                    ->where('relationship', $inverse->value)
                    ->delete();
            }

            $entry->delete();
        });

        DomainAudit::record(DomainAudit::PERSON_RELATIONSHIP_REMOVED, $record, [
            'relatedPersonId' => $entry->related_person_id,
            'relationship' => $entry->relationship,
        ]);

        return back()->with('success', 'Relación familiar eliminada.');
    }

    private function findPerson(string $person): Person
    {
        return Person::query()->findOrFail($person);
    }
}
