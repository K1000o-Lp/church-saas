<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/register');
    }

    public function store(RegisterUserRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, password: string} $data */
        $data = $request->validated();

        $user = User::create($data);

        $mailFailed = false;

        try {
            event(new Registered($user));
        } catch (Throwable $exception) {
            report($exception);
            $mailFailed = true;
        }

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        $response = redirect()
            ->route('verification.notice')
            ->with('success', 'Tu cuenta está creada. Verifica tu correo para configurar tu iglesia.');

        if ($mailFailed) {
            $response->with('error', 'No pudimos enviar el correo de verificación. Usa reenviar en esta pantalla.');
        }

        return $response;
    }
}
