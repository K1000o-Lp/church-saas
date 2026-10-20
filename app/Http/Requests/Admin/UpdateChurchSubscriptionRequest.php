<?php

namespace App\Http\Requests\Admin;

use App\Support\Plans\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UpdateChurchSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::guard('platform')->check();
    }

    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('notes'));

        $this->merge([
            'status' => trim((string) $this->input('status')),
            'notes' => $notes === '' ? null : $notes,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'status' => ['required', 'string', Rule::in(SubscriptionStatus::all())],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'Selecciona un plan.',
            'plan_id.exists' => 'Selecciona un plan activo.',
            'status.in' => 'Selecciona un estado de suscripción válido.',
        ];
    }
}
