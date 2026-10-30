<?php

namespace App\Support\Auth;

final class RolePermissions
{
    /**
     * @return array<string, array<int, Permission>>
     */
    public static function matrix(): array
    {
        return [
            Role::Admin->value => Permission::cases(),

            Role::Pastor->value => [
                Permission::PeopleView,
                Permission::PeopleCreate,
                Permission::PeopleUpdate,
                Permission::PeopleArchive,
                Permission::PastoralView,
                Permission::PastoralManage,
                Permission::AppointmentsView,
                Permission::AppointmentsManage,
                Permission::AppointmentsNotesView,
                Permission::EventsView,
                Permission::EventsManage,
                Permission::AttendanceRecord,
                Permission::SegmentsView,
                Permission::SegmentsManage,
                Permission::TeamsView,
                Permission::TeamsManage,
                Permission::VolunteersManage,
                Permission::WorkflowsView,
                Permission::WorkflowsManage,
            ],

            Role::Finance->value => [
                Permission::ChurchPlanView,
                Permission::BillingView,
                Permission::FinanceView,
                Permission::FinanceRecord,
                Permission::FinanceReports,
            ],

            Role::Leader->value => [
                Permission::PeopleView,
                Permission::AppointmentsView,
                Permission::AppointmentsManage,
                Permission::AppointmentsNotesView,
                Permission::EventsView,
                Permission::EventsManage,
                Permission::AttendanceRecord,
                Permission::SegmentsView,
                Permission::TeamsView,
                Permission::TeamsManage,
                Permission::VolunteersManage,
            ],

            Role::Volunteer->value => [
                Permission::EventsView,
                Permission::AttendanceRecord,
            ],

            Role::Member->value => [
                Permission::EventsView,
            ],
        ];
    }

    /**
     * @return array<int, Permission>
     */
    public static function forRole(Role|string|null $role): array
    {
        if (is_string($role)) {
            $role = Role::tryFrom($role);
        }

        return $role === null ? [] : (self::matrix()[$role->value] ?? []);
    }

    public static function allows(Role|string|null $role, Permission|string $permission): bool
    {
        if (is_string($permission)) {
            $permission = Permission::tryFrom($permission);
        }

        if ($permission === null) {
            return false;
        }

        return in_array($permission, self::forRole($role), true);
    }

    /**
     * @return array{value: string, label: string}
     */
    public static function options(): array
    {
        return array_map(fn (Role $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
        ], Role::cases());
    }
}
