<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::AttendanceRecord);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Church $church */
        $church = $this->route('church');

        return [
            'event_registration_id' => ['nullable', 'integer'],
            'person_id' => [
                'nullable',
                'integer',
                Rule::exists('people', 'id')->where('church_id', $church->getKey()),
            ],
            'status' => ['required', 'string', Rule::in(['present', 'absent', 'late', 'excused'])],
            'source' => ['sometimes', 'string', Rule::in(['manual', 'import', 'device'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('event_registration_id') === null && $this->input('person_id') === null) {
                $validator->errors()->add('person_id', 'Selecciona una persona o una inscripción.');
            }
        });
    }
}
