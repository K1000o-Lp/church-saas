<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workflow;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class WorkflowPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows(Permission::WorkflowsView);
    }

    public function view(User $user, Workflow $workflow): bool
    {
        return $this->allows(Permission::WorkflowsView) && $this->sameChurch($workflow);
    }

    public function create(User $user): bool
    {
        return $this->allows(Permission::WorkflowsManage);
    }

    public function update(User $user, Workflow $workflow): bool
    {
        return $this->allows(Permission::WorkflowsManage) && $this->sameChurch($workflow);
    }

    public function operate(User $user, Workflow $workflow): bool
    {
        return $this->allows(Permission::WorkflowsOperate) && $this->sameChurch($workflow);
    }

    private function allows(Permission $permission): bool
    {
        return app(CurrentChurch::class)->allows($permission);
    }

    private function sameChurch(Workflow $workflow): bool
    {
        return (string) app(CurrentChurch::class)->id() === (string) $workflow->church_id;
    }
}
