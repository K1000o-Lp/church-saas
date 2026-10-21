<?php

namespace App\Policies;

use App\Models\Segment;
use App\Models\User;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class SegmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows(Permission::SegmentsView);
    }

    public function view(User $user, Segment $segment): bool
    {
        return $this->allows(Permission::SegmentsView)
            && $this->sameChurch($segment);
    }

    public function create(User $user): bool
    {
        return $this->allows(Permission::SegmentsManage);
    }

    public function update(User $user, Segment $segment): bool
    {
        return $this->allows(Permission::SegmentsManage)
            && $this->sameChurch($segment);
    }

    public function delete(User $user, Segment $segment): bool
    {
        return $this->update($user, $segment);
    }

    private function allows(Permission $permission): bool
    {
        return app(CurrentChurch::class)->allows($permission);
    }

    private function sameChurch(Segment $segment): bool
    {
        return (string) app(CurrentChurch::class)->id() === (string) $segment->church_id;
    }
}
