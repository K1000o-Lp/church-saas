<?php

namespace Tests\Unit;

use App\Support\Auth\Permission;
use App\Support\Auth\Role;
use App\Support\Auth\RolePermissions;
use PHPUnit\Framework\TestCase;

class RolePermissionsTest extends TestCase
{
    public function test_admin_receives_the_full_permission_catalog(): void
    {
        $this->assertCount(
            count(Permission::cases()),
            RolePermissions::forRole(Role::Admin),
        );

        foreach (Permission::cases() as $permission) {
            $this->assertTrue(RolePermissions::allows(Role::Admin, $permission));
        }
    }

    public function test_finance_can_manage_billing_but_not_members_or_people(): void
    {
        $this->assertTrue(RolePermissions::allows(Role::Finance, Permission::BillingView));
        $this->assertTrue(RolePermissions::allows(Role::Finance, Permission::FinanceRecord));
        $this->assertFalse(RolePermissions::allows(Role::Finance, Permission::ChurchMembersManage));
        $this->assertFalse(RolePermissions::allows(Role::Finance, Permission::PeopleView));
    }

    public function test_leader_can_view_people_but_not_create_or_update_them(): void
    {
        $this->assertTrue(RolePermissions::allows(Role::Leader, Permission::PeopleView));
        $this->assertFalse(RolePermissions::allows(Role::Leader, Permission::PeopleCreate));
        $this->assertFalse(RolePermissions::allows(Role::Leader, Permission::PeopleUpdate));
    }

    public function test_unknown_or_missing_roles_have_no_permissions(): void
    {
        $this->assertSame([], RolePermissions::forRole('unknown'));
        $this->assertSame([], RolePermissions::forRole(null));
        $this->assertFalse(RolePermissions::allows('unknown', Permission::PeopleView));
    }
}
