<?php

namespace App\Http\Middleware;

use App\Support\Plans\Entitlements;
use App\Support\Tenancy\CurrentChurch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $church = app(CurrentChurch::class)->church();

        abort_unless($church !== null, 404);

        if (! Entitlements::forChurch($church)->can($feature)) {
            abort(403, 'El plan actual de la iglesia no incluye esta función.');
        }

        return $next($request);
    }
}
