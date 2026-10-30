<?php

namespace App\Policies;

use App\Models\ServicePlan;
use App\Models\User;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class ServicePlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows(Permission::TeamsView);
    }

    public function view(User $user, ServicePlan $plan): bool
    {
        return $this->allows(Permission::TeamsView) && $this->sameChurch($plan);
    }

    public function create(User $user): bool
    {
        return $this->allows(Permission::TeamsManage);
    }

    public function update(User $user, ServicePlan $plan): bool
    {
        return $this->allows(Permission::TeamsManage) && $this->sameChurch($plan);
    }

    private function allows(Permission $permission): bool
    {
        return app(CurrentChurch::class)->allows($permission);
    }

    private function sameChurch(ServicePlan $plan): bool
    {
        return (string) app(CurrentChurch::class)->id() === (string) $plan->church_id;
    }
}
