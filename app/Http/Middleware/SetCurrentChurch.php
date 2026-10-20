<?php

namespace App\Http\Middleware;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Support\Tenancy\CurrentChurch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentChurch
{
    public function __construct(private readonly CurrentChurch $currentChurch) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->currentChurch->clear();

        $church = $request->route('church');
        $user = $request->user();

        abort_unless($church instanceof Church && $user !== null, 404);
        abort_unless($church->status === 'active', 404);

        $isMember = ChurchMembership::query()
            ->where('church_id', $church->getKey())
            ->where('user_id', $user->getAuthIdentifier())
            ->where('status', 'active')
            ->exists();

        abort_unless($isMember, 404);

        $this->currentChurch->set($church);

        try {
            return $next($request);
        } finally {
            $this->currentChurch->clear();
        }
    }
}
