<?php

namespace App\Policies;

use App\Models\Church;
use App\Models\User;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class ChurchPolicy
{
    public function viewPlan(User $user, Church $church): bool
    {
        return $this->allows(Permission::ChurchPlanView, $church);
    }

    public function managePlan(User $user, Church $church): bool
    {
        return $this->allows(Permission::ChurchPlanManage, $church);
    }

    public function viewBilling(User $user, Church $church): bool
    {
        return $this->allows(Permission::BillingView, $church);
    }

    public function manageSettings(User $user, Church $church): bool
    {
        return $this->allows(Permission::ChurchSettingsManage, $church);
    }

    public function manageMembers(User $user, Church $church): bool
    {
        return $this->allows(Permission::ChurchMembersManage, $church);
    }

    public function viewAudit(User $user, Church $church): bool
    {
        return $this->allows(Permission::ChurchAuditView, $church);
    }

    private function allows(Permission $permission, Church $church): bool
    {
        $current = app(CurrentChurch::class);

        return (string) $current->id() === (string) $church->getKey()
            && $current->allows($permission);
    }
}
