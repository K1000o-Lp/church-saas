<?php

namespace App\Http\Requests;

use App\Models\ChurchInvitation;
use App\Support\Auth\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreChurchInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', ChurchInvitation::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'role' => trim((string) $this->input('role')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(Role::values())],
        ];
    }
}
