<?php

namespace App\Http\Requests\Admin;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => Str::lower($this->string('email')->trim()->toString()),
            'password' => $this->string('password')->toString(),
        ];

        if (! Auth::guard('platform')->attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Estas credenciales no coinciden con una cuenta de plataforma.',
            ]);
        }

        /** @var PlatformAdmin $admin */
        $admin = Auth::guard('platform')->user();

        if (! $admin->is_active) {
            Auth::guard('platform')->logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'Esta cuenta de plataforma está desactivada.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $admin->forceFill(['last_login_at' => now()])->save();
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Demasiados intentos. Intenta de nuevo en {$seconds} segundos.",
        ]);
    }

    private function throttleKey(): string
    {
        return 'admin|'.Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
