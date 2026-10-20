<?php

namespace App\Support\Plans;

use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Models\Event;
use App\Models\Person;
use App\Models\Workflow;
use App\Models\WorkflowRun;

final class ChurchUsage
{
    /**
     * @return array<string, int>
     */
    public static function forChurch(Church|int $church): array
    {
        return self::forChurchId($church instanceof Church ? (int) $church->getKey() : $church);
    }

    /**
     * @return array<string, int>
     */
    public static function forChurchId(int $churchId): array
    {
        return collect(PlanResources::keys())
            ->mapWithKeys(fn (string $resource): array => [
                $resource => self::count($churchId, $resource),
            ])
            ->all();
    }

    public static function count(int $churchId, string $resource): int
    {
        return match ($resource) {
            PlanResources::PEOPLE => Person::withoutGlobalScope('church')
                ->where('church_id', $churchId)
                ->where('status', '!=', 'archived')
                ->count(),
            PlanResources::ACTIVE_USERS => ChurchMembership::query()
                ->where('church_id', $churchId)
                ->where('status', 'active')
                ->count(),
            PlanResources::CURRENCIES => ChurchCurrency::withoutGlobalScope('church')
                ->where('church_id', $churchId)
                ->count(),
            PlanResources::EVENTS_PER_MONTH => Event::withoutGlobalScope('church')
                ->where('church_id', $churchId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('created_at', '<', now()->startOfMonth()->addMonth())
                ->count(),
            PlanResources::WORKFLOWS => Workflow::withoutGlobalScope('church')
                ->where('church_id', $churchId)
                ->count(),
            PlanResources::EXECUTIONS_PER_MONTH => WorkflowRun::withoutGlobalScope('church')
                ->where('church_id', $churchId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->where('created_at', '<', now()->startOfMonth()->addMonth())
                ->count(),
            // Integrations are not implemented yet; entitlement exists for the approved matrix.
            PlanResources::INTEGRATIONS => 0,
            default => 0,
        };
    }
}
