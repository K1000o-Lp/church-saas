<?php

namespace App\Http\Middleware;

use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChurchPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $required = Permission::tryFrom($permission);

        abort_if($required === null, 500, 'El permiso solicitado no existe.');

        $currentChurch = app(CurrentChurch::class);

        abort_unless($currentChurch->church() !== null, 404);
        abort_unless(
            $currentChurch->allows($required),
            403,
            'No tienes permiso para realizar esta acción en esta iglesia.',
        );

        return $next($request);
    }
}
