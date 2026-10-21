<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VerifyEmailController extends Controller
{
    public function __invoke(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::query()->findOrFail($id);

        abort_unless(
            (string) $request->user()?->getAuthIdentifier() === (string) $user->getKey(),
            403,
        );

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();

            event(new Verified($user));
        }

        $hasMembership = $user->churchMemberships()
            ->where('status', 'active')
            ->exists();

        return redirect()
            ->intended($hasMembership ? route('dashboard') : route('onboarding.church'))
            ->with('success', 'Correo verificado. Ya puedes continuar.');
    }
}
