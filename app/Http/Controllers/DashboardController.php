<?php

namespace App\Http\Controllers;

use App\Models\ChurchMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $memberships = ChurchMembership::query()
            ->with('church')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('status', 'active')
            ->whereHas('church', fn ($query) => $query->where('status', 'active'))
            ->orderBy('church_id')
            ->get();

        if ($memberships->count() === 1) {
            return redirect()->route('churches.show', $memberships->first()->church);
        }

        return Inertia::render('dashboard', [
            'memberships' => $memberships->map(fn (ChurchMembership $membership): array => [
                'id' => $membership->getKey(),
                'role' => $membership->role,
                'church' => [
                    'name' => $membership->church->name,
                    'slug' => $membership->church->slug,
                    'countryCode' => $membership->church->country_code,
                    'currencyCode' => $membership->church->currency_code,
                    'timezone' => $membership->church->timezone,
                ],
            ])->values(),
        ]);
    }
}
