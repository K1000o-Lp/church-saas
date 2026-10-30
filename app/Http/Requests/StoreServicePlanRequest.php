<?php

namespace App\Http\Requests;

use App\Support\Auth\Permission;
use App\Support\Services\ServiceStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServicePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::TeamsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'location' => trim((string) $this->input('location')) ?: null,
            'starts_at' => $this->input('starts_at') ?: null,
            'ends_at' => $this->input('ends_at') ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in([ServiceStatus::Draft->value, ServiceStatus::Published->value])],
        ];
    }
}
