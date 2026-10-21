<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

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

        $user->forceFill(['email_verified_at' => now()])->save();

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        return redirect()
            ->route('onboarding.church')
            ->with('success', 'Tu cuenta está creada. Configura tu iglesia para empezar.');
    }
}
