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
        Password::sendResetLink($request->only('email'));

        // Never reveal whether the email belongs to an account, including
        // the broker's RESET_THROTTLED response which only exists for users.
        return back()->with(
            'success',
            'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
        );
    }
}
