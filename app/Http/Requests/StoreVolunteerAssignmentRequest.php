<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\Auth\Permission;
use App\Support\Services\AssignmentStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVolunteerAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::VolunteersManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'role' => trim((string) $this->input('role')) ?: null,
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
                'required',
                'integer',
                Rule::exists('people', 'id')->where('church_id', $church->getKey()),
            ],
            'role' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'string', Rule::in(AssignmentStatus::values())],
        ];
    }
}
