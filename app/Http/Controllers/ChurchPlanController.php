<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateChurchPlanRequest;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ChurchSubscription;
use App\Models\Plan;
use App\Support\Audit\DomainAudit;
use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanCatalog;
use App\Support\Plans\SubscriptionStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'availablePlans' => PlanCatalog::available($entitlements->plan()?->key),
            'canChangePlan' => Gate::allows('managePlan', $church),
        ]);
    }

    public function update(UpdateChurchPlanRequest $request, Church $church): RedirectResponse
    {
        Gate::authorize('managePlan', $church);

        /** @var array{plan: string} $data */
        $data = $request->validated();

        $plan = Plan::query()
            ->where('key', $data['plan'])
            ->where('is_active', true)
            ->firstOrFail();

        $changed = DB::transaction(function () use ($church, $plan, $request): bool {
            $subscription = ChurchSubscription::query()
                ->where('church_id', $church->getKey())
                ->lockForUpdate()
                ->first();

            if (
                $subscription !== null
                && (int) $subscription->plan_id === (int) $plan->getKey()
                && $subscription->status === SubscriptionStatus::ACTIVE
            ) {
                return false;
            }

            $before = [
                'planId' => $subscription?->plan_id,
                'status' => $subscription?->status,
            ];

            $subscription ??= new ChurchSubscription([
                'church_id' => $church->getKey(),
                'started_at' => now(),
            ]);

            $subscription->fill([
                'plan_id' => $plan->getKey(),
                'status' => SubscriptionStatus::ACTIVE,
                'plan_changed_at' => now(),
                'assigned_by_platform_admin_id' => null,
            ])->save();

            DomainAudit::recordForChurch(
                $church,
                DomainAudit::PLAN_CHANGED,
                $subscription,
                [
                    'before' => $before,
                    'after' => [
                        'planId' => $subscription->plan_id,
                        'planKey' => $plan->key,
                        'status' => $subscription->status,
                    ],
                ],
                $request->user(),
            );

            return true;
        });

        return redirect()
            ->route('churches.plan', $church)
            ->with(
                'success',
                $changed
                    ? "Plan actualizado a {$plan->name}. La facturación se coordina por separado con la plataforma."
                    : 'La iglesia ya usa ese plan.',
            );
    }
}
