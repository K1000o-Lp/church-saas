<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/forgot-password');
    }

    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return back()->withErrors([
                'email' => 'Espera un momento antes de solicitar otro enlace.',
            ]);
        }

        // Do not reveal whether the email belongs to an account.
        return back()->with(
            'success',
            'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
        );
    }
}
