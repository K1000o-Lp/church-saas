<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows(Permission::EventsView);
    }

    public function view(User $user, Event $event): bool
    {
        return $this->allows(Permission::EventsView) && $this->sameChurch($event);
    }

    public function create(User $user): bool
    {
        return $this->allows(Permission::EventsManage);
    }

    public function update(User $user, Event $event): bool
    {
        return $this->allows(Permission::EventsManage) && $this->sameChurch($event);
    }

    public function publish(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function cancel(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    public function complete(User $user, Event $event): bool
    {
        return $this->update($user, $event);
    }

    private function allows(Permission $permission): bool
    {
        return app(CurrentChurch::class)->allows($permission);
    }

    private function sameChurch(Event $event): bool
    {
        return (string) app(CurrentChurch::class)->id() === (string) $event->church_id;
    }
}
