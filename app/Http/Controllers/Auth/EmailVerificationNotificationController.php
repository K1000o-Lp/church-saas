<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        try {
            $request->user()?->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with(
                'error',
                'No pudimos enviar el enlace de verificación. Inténtalo de nuevo en un momento.',
            );
        }

        return back()->with(
            'success',
            'Te enviamos un nuevo enlace de verificación. Revisa tu correo.',
        );
    }
}
