<?php

namespace App\Http\Middleware;

use App\Models\PlatformAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('platform')->user();

        if (! $admin instanceof PlatformAdmin || ! $admin->is_active) {
            Auth::guard('platform')->logout();

            return redirect()
                ->route('admin.login')
                ->with('error', 'Inicia sesión con una cuenta de plataforma activa.');
        }

        $request->attributes->set('platformAdmin', $admin);

        return $next($request);
    }
}
