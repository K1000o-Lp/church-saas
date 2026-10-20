<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Support\Plans\Entitlements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChurchPlanController extends Controller
{
    public function show(Request $request, Church $church): Response
    {
        /** @var ChurchMembership $membership */
        $membership = $request->attributes->get('currentMembership');

        Gate::authorize('viewPlan', $church);

        $entitlements = Entitlements::forChurch($church);

        return Inertia::render('churches/plan', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'membership' => [
                'role' => $membership->role,
            ],
            'plan' => $entitlements->toArray(),
        ]);
    }
}
