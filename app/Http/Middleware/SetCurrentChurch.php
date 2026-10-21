<?php

namespace App\Http\Middleware;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Support\Church\ChurchStatus;
use App\Support\Tenancy\CurrentChurch;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentChurch
{
    public function __construct(private readonly CurrentChurch $currentChurch) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentChurch->clear();

        $user = $request->user();

        abort_unless($user !== null, 404);

        $church = $request->route('church');

        if (! $church instanceof Church) {
            $church = $this->selectedChurch($request);
        }

        abort_unless($church instanceof Church, 404);

        $membership = ChurchMembership::query()
            ->where('church_id', $church->getKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', 'active')
            ->first();

        abort_unless($membership !== null, 404);

        if (ChurchStatus::blocksAccess($church->status)) {
            $request->session()->forget('current_church_id');

            return Inertia::render('churches/unavailable', [
                'church' => [
                    'name' => $church->name,
                    'slug' => $church->slug,
                ],
                'status' => $church->status,
                'statusLabel' => ChurchStatus::label($church->status),
            ])->toResponse($request)->setStatusCode(403);
        }

        $request->session()->put('current_church_id', $church->getKey());
        $request->attributes->set('currentChurch', $church);
        $request->attributes->set('currentMembership', $membership);

        $this->currentChurch->set($church, $membership);

        try {
            return $next($request);
        } finally {
            $this->currentChurch->clear();
        }
    }

    private function selectedChurch(Request $request): ?Church
    {
        $churchId = $request->session()->get('current_church_id');

        if ($churchId === null) {
            return null;
        }

        $church = Church::query()->find($churchId);

        if ($church === null) {
            $request->session()->forget('current_church_id');
        }

        return $church;
    }
}
