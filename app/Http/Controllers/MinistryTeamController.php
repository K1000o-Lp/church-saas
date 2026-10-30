<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMinistryTeamRequest;
use App\Http\Requests\UpdateMinistryTeamRequest;
use App\Models\Church;
use App\Models\MinistryTeam;
use App\Support\Audit\DomainAudit;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MinistryTeamController extends Controller
{
    public function index(Church $church): Response
    {
        return Inertia::render('services/teams', [
            'church' => $this->churchPayload($church),
            'teams' => MinistryTeam::query()
                ->withCount('serviceItems')
                ->orderBy('name')
                ->get()
                ->map(fn (MinistryTeam $team): array => [
                    'id' => $team->getKey(),
                    'name' => $team->name,
                    'description' => $team->description,
                    'isActive' => $team->is_active,
                    'itemsCount' => (int) ($team->service_items_count ?? 0),
                ])
                ->values(),
            'canManage' => app(CurrentChurch::class)->allows(Permission::TeamsManage),
        ]);
    }

    public function store(StoreMinistryTeamRequest $request, Church $church): RedirectResponse
    {
        $data = $request->validated();

        $team = MinistryTeam::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        DomainAudit::record(DomainAudit::TEAM_CREATED, $team, [
            'name' => $team->name,
        ]);

        return back()->with('success', 'Equipo creado.');
    }

    public function update(
        UpdateMinistryTeamRequest $request,
        Church $church,
        string $team,
    ): RedirectResponse {
        $record = MinistryTeam::query()->findOrFail($team);
        $data = $request->validated();

        $record->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? $record->is_active),
        ])->save();

        DomainAudit::record(DomainAudit::TEAM_UPDATED, $record, [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ]);

        return back()->with('success', 'Equipo actualizado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function churchPayload(Church $church): array
    {
        return [
            'name' => $church->name,
            'slug' => $church->slug,
        ];
    }
}
