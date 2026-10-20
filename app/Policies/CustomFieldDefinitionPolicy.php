<?php

namespace App\Policies;

use App\Models\CustomFieldDefinition;
use App\Models\User;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class CustomFieldDefinitionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows();
    }

    public function create(User $user): bool
    {
        return $this->allows();
    }

    public function update(User $user, CustomFieldDefinition $definition): bool
    {
        return $this->allows()
            && (string) app(CurrentChurch::class)->id() === (string) $definition->church_id;
    }

    public function delete(User $user, CustomFieldDefinition $definition): bool
    {
        return $this->update($user, $definition);
    }

    private function allows(): bool
    {
        return app(CurrentChurch::class)->allows(Permission::PeopleCustomFieldsManage);
    }
}
