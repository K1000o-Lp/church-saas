<?php

namespace App\Http\Requests;

use App\Models\Church;
use App\Support\Auth\Permission;
use App\Support\Events\EventStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::EventsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'description' => trim((string) $this->input('description')) ?: null,
            'location' => trim((string) $this->input('location')) ?: null,
            'timezone' => trim((string) $this->input('timezone')) ?: null,
            'starts_at' => $this->input('starts_at') ?: null,
            'ends_at' => $this->input('ends_at') ?: null,
            'capacity' => $this->input('capacity') ?: null,
            'price' => $this->input('price') === '' ? null : $this->input('price'),
            'currency_code' => $this->input('currency_code') ?: null,
            'registration_opens_at' => $this->input('registration_opens_at') ?: null,
            'registration_closes_at' => $this->input('registration_closes_at') ?: null,
            'is_public' => $this->boolean('is_public'),
            'is_paid' => $this->boolean('is_paid'),
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
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['nullable', 'timezone'],
            'status' => ['required', 'string', Rule::in([EventStatus::Draft->value, EventStatus::Published->value])],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'is_public' => ['boolean'],
            'is_paid' => ['boolean'],
            'price' => ['nullable', 'required_if:is_paid,true', 'numeric', 'min:0', 'regex:/^\d{1,10}(?:\.\d{1,2})?$/'],
            'currency_code' => [
                'nullable',
                'required_if:is_paid,true',
                'string',
                Rule::exists('church_currencies', 'currency_code')->where('church_id', $church->getKey()),
            ],
            'registration_opens_at' => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after_or_equal:registration_opens_at'],
        ];
    }
}
