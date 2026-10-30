<?php

namespace App\Support\Auth;

enum Permission: string
{
    case ChurchSettingsManage = 'church.settings.manage';
    case ChurchMembersManage = 'church.members.manage';
    case ChurchInvitationsManage = 'church.invitations.manage';
    case ChurchAuditView = 'church.audit.view';
    case ChurchPlanView = 'church.plan.view';
    case ChurchPlanManage = 'church.plan.manage';
    case BillingView = 'billing.view';

    case PeopleView = 'people.view';
    case PeopleCreate = 'people.create';
    case PeopleUpdate = 'people.update';
    case PeopleArchive = 'people.archive';
    case PeopleCustomFieldsManage = 'people.custom_fields.manage';

    case PastoralView = 'pastoral.view';
    case PastoralManage = 'pastoral.manage';

    case AppointmentsView = 'appointments.view';
    case AppointmentsManage = 'appointments.manage';
    case AppointmentsNotesView = 'appointments.notes.view';

    case EventsView = 'events.view';
    case EventsManage = 'events.manage';
    case AttendanceRecord = 'attendance.record';

    case SegmentsView = 'segments.view';
    case SegmentsManage = 'segments.manage';

    case TeamsView = 'teams.view';
    case TeamsManage = 'teams.manage';
    case VolunteersManage = 'volunteers.manage';

    case FinanceView = 'finance.view';
    case FinanceRecord = 'finance.record';
    case FinanceReports = 'finance.reports';

    case WorkflowsView = 'workflows.view';
    case WorkflowsManage = 'workflows.manage';
    case WorkflowsOperate = 'workflows.operate';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
