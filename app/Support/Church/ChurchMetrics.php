<?php

namespace App\Support\Church;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Event;
use App\Models\Person;
use App\Models\ServicePlan;
use App\Models\Workflow;
use Illuminate\Support\Collection;

final class ChurchMetrics
{
    /**
     * @param  Collection<int, Church>|iterable<Church>  $churches
     * @return array<int, array{activeMemberships: int, people: int, events: int, servicePlans: int, workflows: int}>
     */
    public static function forChurches(iterable $churches): array
    {
        $ids = collect($churches)->pluck('id')->filter()->values()->all();

        if ($ids === []) {
            return [];
        }

        $memberships = ChurchMembership::query()
            ->selectRaw('church_id, count(*) as aggregate')
            ->whereIn('church_id', $ids)
            ->where('status', 'active')
            ->groupBy('church_id')
            ->pluck('aggregate', 'church_id');

        $people = Person::withoutGlobalScope('church')
            ->selectRaw('church_id, count(*) as aggregate')
            ->whereIn('church_id', $ids)
            ->where('status', '!=', 'archived')
            ->groupBy('church_id')
            ->pluck('aggregate', 'church_id');

        $events = Event::withoutGlobalScope('church')
            ->selectRaw('church_id, count(*) as aggregate')
            ->whereIn('church_id', $ids)
            ->groupBy('church_id')
            ->pluck('aggregate', 'church_id');

        $servicePlans = ServicePlan::withoutGlobalScope('church')
            ->selectRaw('church_id, count(*) as aggregate')
            ->whereIn('church_id', $ids)
            ->groupBy('church_id')
            ->pluck('aggregate', 'church_id');

        $workflows = Workflow::withoutGlobalScope('church')
            ->selectRaw('church_id, count(*) as aggregate')
            ->whereIn('church_id', $ids)
            ->groupBy('church_id')
            ->pluck('aggregate', 'church_id');

        return collect($ids)->mapWithKeys(fn (int $id): array => [
            $id => [
                'activeMemberships' => (int) ($memberships[$id] ?? 0),
                'people' => (int) ($people[$id] ?? 0),
                'events' => (int) ($events[$id] ?? 0),
                'servicePlans' => (int) ($servicePlans[$id] ?? 0),
                'workflows' => (int) ($workflows[$id] ?? 0),
            ],
        ])->all();
    }

    /**
     * @return array{activeMemberships: int, people: int, events: int, servicePlans: int, workflows: int}
     */
    public static function forChurch(Church $church): array
    {
        return self::forChurches([$church])[$church->getKey()] ?? [
            'activeMemberships' => 0,
            'people' => 0,
            'events' => 0,
            'servicePlans' => 0,
            'workflows' => 0,
        ];
    }
}
