<?php

namespace App\Policies;

use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class ChurchMembershipPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows(Permission::ChurchMembersManage);
    }

    public function update(User $user, ChurchMembership $membership): bool
    {
        return $this->allows(Permission::ChurchMembersManage) && $this->sameChurch($membership);
    }

    public function delete(User $user, ChurchMembership $membership): bool
    {
        return $this->update($user, $membership);
    }

    private function allows(Permission $permission): bool
    {
        return app(CurrentChurch::class)->allows($permission);
    }

    private function sameChurch(ChurchMembership $membership): bool
    {
        return (string) app(CurrentChurch::class)->id() === (string) $membership->church_id;
    }
}
