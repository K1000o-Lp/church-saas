<?php

namespace App\Http\Requests;

use App\Models\ChurchInvitation;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AcceptChurchInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        if (Auth::guard('web')->check()) {
            return [];
        }

        $token = (string) $this->route('token');
        $invitation = ChurchInvitation::query()
            ->where('token_hash', ChurchInvitation::hashToken($token))
            ->first();

        $hasAccount = $invitation !== null && User::query()
            ->whereRaw('lower(email) = ?', [Str::lower($invitation->email)])
            ->exists();

        if ($hasAccount) {
            return [];
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::default()],
        ];
    }
}
