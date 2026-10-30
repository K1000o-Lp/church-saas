<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::AppointmentsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'subject' => trim((string) $this->input('subject')) ?: null,
            'notes' => trim((string) $this->input('notes')) ?: null,
        ]);

        $currentChurch = app(CurrentChurch::class);

        if (! in_array($currentChurch->role(), ['admin', 'pastor'], true)) {
            $this->merge([
                'host_user_id' => $currentChurch->membership()?->user_id,
            ]);
        }
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
            'host_user_id' => [
                'required',
                'integer',
                Rule::exists('church_memberships', 'user_id')
                    ->where('church_id', $church->getKey())
                    ->where('status', 'active'),
            ],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'subject' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
