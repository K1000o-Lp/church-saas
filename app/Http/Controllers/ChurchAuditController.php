<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\ChurchAuditEntry;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChurchAuditController extends Controller
{
    public function index(Church $church): Response
    {
        Gate::authorize('viewAudit', $church);

        $paginator = ChurchAuditEntry::query()
            ->with('actor')
            ->where('church_id', $church->getKey())
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('churches/settings/audit', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'entries' => $paginator->getCollection()
                ->map(fn (ChurchAuditEntry $entry): array => [
                    'id' => $entry->getKey(),
                    'action' => $entry->action,
                    'actor' => $entry->actor?->name,
                    'actorEmail' => $entry->actor?->email,
                    'actorRole' => $entry->actor_role,
                    'subjectType' => $entry->subject_type,
                    'subjectId' => $entry->subject_id,
                    'metadata' => $entry->metadata ?? [],
                    'createdAt' => $entry->created_at?->toIso8601String(),
                ])
                ->values(),
            'pagination' => [
                'currentPage' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'perPage' => $paginator->perPage(),
                'total' => $paginator->total(),
                'previousPageUrl' => $paginator->previousPageUrl(),
                'nextPageUrl' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
