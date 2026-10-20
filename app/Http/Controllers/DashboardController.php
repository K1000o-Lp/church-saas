<?php

namespace App\Http\Controllers;

use App\Models\ChurchMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
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

        return view('dashboard', ['memberships' => $memberships]);
    }
}
