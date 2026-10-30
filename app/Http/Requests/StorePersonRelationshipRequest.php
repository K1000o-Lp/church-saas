<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\Auth\Permission;
use App\Support\People\RelationshipType;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::PeopleUpdate);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Church $church */
        $church = $this->route('church');
        $personId = (int) $this->route('person');

        return [
            'related_person_id' => [
                'required',
                'integer',
                'not_in:'.$personId,
                Rule::exists('people', 'id')->where('church_id', $church->getKey()),
            ],
            'relationship' => ['required', 'string', Rule::in(RelationshipType::values())],
            'is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'related_person_id.not_in' => 'Una persona no puede relacionarse consigo misma.',
            'related_person_id.exists' => 'Selecciona una persona de esta iglesia.',
        ];
    }
}
