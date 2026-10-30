<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\Auth\Permission;
use App\Support\Events\RegistrationStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::EventsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guest_name' => trim((string) $this->input('guest_name')) ?: null,
            'person_id' => $this->input('person_id') ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Church $church */
        $church = $this->route('church');

        return [
            'person_id' => [
                'nullable',
                'integer',
                Rule::exists('people', 'id')->where('church_id', $church->getKey()),
            ],
            'guest_name' => ['nullable', 'string', 'max:120'],
            'guest_count' => ['required', 'integer', 'min:0', 'max:20'],
            'status' => ['required', 'string', Rule::in(RegistrationStatus::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('person_id') === null && $this->input('guest_name') === null) {
                $validator->errors()->add('person_id', 'Selecciona una persona o registra un invitado.');
            }
        });
    }
}
