<?php

namespace App\Policies;

use App\Models\ChurchInvitation;
use App\Models\User;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class ChurchInvitationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows();
    }

    public function create(User $user): bool
    {
        return $this->allows();
    }

    public function update(User $user, ChurchInvitation $invitation): bool
    {
        return $this->allows()
            && (string) app(CurrentChurch::class)->id() === (string) $invitation->church_id;
    }

    public function delete(User $user, ChurchInvitation $invitation): bool
    {
        return $this->update($user, $invitation);
    }

    private function allows(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::ChurchInvitationsManage);
    }
}
