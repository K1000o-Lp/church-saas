<?php

namespace App\Policies;

use App\Models\Person;
use App\Models\User;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class PersonPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows(Permission::PeopleView);
    }

    public function view(User $user, Person $person): bool
    {
        return $this->allows(Permission::PeopleView) && $this->sameChurch($person);
    }

    public function create(User $user): bool
    {
        return $this->allows(Permission::PeopleCreate);
    }

    public function update(User $user, Person $person): bool
    {
        return $this->allows(Permission::PeopleUpdate) && $this->sameChurch($person);
    }

    public function archive(User $user, Person $person): bool
    {
        return $this->allows(Permission::PeopleArchive) && $this->sameChurch($person);
    }

    public function archiveAny(User $user): bool
    {
        return $this->allows(Permission::PeopleArchive);
    }

    public function updateAny(User $user): bool
    {
        return $this->allows(Permission::PeopleUpdate);
    }

    private function allows(Permission $permission): bool
    {
        return app(CurrentChurch::class)->allows($permission);
    }

    private function sameChurch(Person $person): bool
    {
        return (string) app(CurrentChurch::class)->id() === (string) $person->church_id;
    }
}
