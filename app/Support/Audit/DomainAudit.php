<?php

namespace App\Support\Audit;

use App\Models\Church;
use App\Models\ChurchAuditEntry;
use App\Models\User;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

final class DomainAudit
{
    public const PERSON_CREATED = 'person.created';

    public const PERSON_UPDATED = 'person.updated';

    public const PERSON_ARCHIVED = 'person.archived';

    public const PERSON_RESTORED = 'person.restored';

    public const CUSTOM_FIELD_CREATED = 'custom_field.created';

    public const CUSTOM_FIELD_UPDATED = 'custom_field.updated';

    public const CUSTOM_FIELD_DEACTIVATED = 'custom_field.deactivated';

    public const SEGMENT_CREATED = 'segment.created';

    public const SEGMENT_UPDATED = 'segment.updated';

    public const SEGMENT_DELETED = 'segment.deleted';

    public const SEGMENT_MEMBERS_CHANGED = 'segment.members_changed';

    public const MEMBERSHIP_ROLE_CHANGED = 'membership.role_changed';

    public const MEMBERSHIP_REMOVED = 'membership.removed';

    public const INVITATION_CREATED = 'invitation.created';

    public const INVITATION_REVOKED = 'invitation.revoked';

    public const INVITATION_ACCEPTED = 'invitation.accepted';

    public const PASSWORD_RESET = 'auth.password_reset';

    public const PLAN_CHANGED = 'plan.changed';

    public const CHURCH_BRANDING_UPDATED = 'church.branding_updated';

    public const PERSON_RELATIONSHIP_ADDED = 'person.relationship_added';

    public const PERSON_RELATIONSHIP_REMOVED = 'person.relationship_removed';

    public const APPOINTMENT_CREATED = 'appointment.created';

    public const APPOINTMENT_UPDATED = 'appointment.updated';

    public const APPOINTMENT_COMPLETED = 'appointment.completed';

    public const APPOINTMENT_CANCELLED = 'appointment.cancelled';

    public const APPOINTMENT_RESCHEDULED = 'appointment.rescheduled';

    public const APPOINTMENT_NO_SHOW = 'appointment.no_show';

    public const EVENT_CREATED = 'event.created';

    public const EVENT_UPDATED = 'event.updated';

    public const EVENT_PUBLISHED = 'event.published';

    public const EVENT_CANCELLED = 'event.cancelled';

    public const EVENT_COMPLETED = 'event.completed';

    public const REGISTRATION_CREATED = 'registration.created';

    public const REGISTRATION_STATUS_CHANGED = 'registration.status_changed';

    public const ATTENDANCE_RECORDED = 'attendance.recorded';

    public const TEAM_CREATED = 'team.created';

    public const TEAM_UPDATED = 'team.updated';

    public const SERVICE_CREATED = 'service.created';

    public const SERVICE_UPDATED = 'service.updated';

    public const SERVICE_PUBLISHED = 'service.published';

    public const SERVICE_CANCELLED = 'service.cancelled';

    public const SERVICE_COMPLETED = 'service.completed';

    public const SERVICE_ITEM_CREATED = 'service.item_created';

    public const SERVICE_ITEM_UPDATED = 'service.item_updated';

    public const SERVICE_ITEM_DELETED = 'service.item_deleted';

    public const VOLUNTEER_ASSIGNED = 'volunteer.assigned';

    public const VOLUNTEER_STATUS_CHANGED = 'volunteer.status_changed';

    public const PIPELINE_STAGE_CREATED = 'pipeline.stage_created';

    public const PIPELINE_STAGE_UPDATED = 'pipeline.stage_updated';

    public const PIPELINE_STAGE_COMPLETED = 'pipeline.stage_completed';

    public const PIPELINE_STAGE_REOPENED = 'pipeline.stage_reopened';

    public const RECEIPT_SUBMITTED = 'receipt.submitted';

    public const RECEIPT_VERIFIED = 'receipt.verified';

    public const RECEIPT_REJECTED = 'receipt.rejected';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        string $action,
        Model $subject,
        array $metadata = [],
        ?User $actor = null,
    ): ChurchAuditEntry {
        $churchId = $subject->getAttribute('church_id');

        if ($churchId === null) {
            $churchId = app(CurrentChurch::class)->id();
        }

        if ($churchId === null) {
            throw new RuntimeException('A church context is required to write a domain audit entry.');
        }

        $currentChurch = app(CurrentChurch::class);
        $resolvedActor = $actor ?? Auth::guard('web')->user();

        return self::write(
            (int) $churchId,
            $resolvedActor instanceof User ? $resolvedActor : null,
            $currentChurch->role(),
            $action,
            $subject::class,
            $subject->getKey(),
            $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function recordForChurch(
        Church $church,
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?User $actor = null,
    ): ChurchAuditEntry {
        $currentChurch = app(CurrentChurch::class);
        $resolvedActor = $actor ?? Auth::guard('web')->user();

        return self::write(
            (int) $church->getKey(),
            $resolvedActor instanceof User ? $resolvedActor : null,
            $currentChurch->id() === $church->getKey() ? $currentChurch->role() : null,
            $action,
            $subject?->getMorphClass(),
            $subject?->getKey(),
            $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function write(
        int $churchId,
        ?User $actor,
        ?string $actorRole,
        string $action,
        ?string $subjectType,
        int|string|null $subjectId,
        array $metadata,
    ): ChurchAuditEntry {
        $runningInConsole = app()->runningInConsole();
        $metadata = self::sanitize($metadata);

        return ChurchAuditEntry::create([
            'church_id' => $churchId,
            'actor_user_id' => $actor?->getKey(),
            'actor_role' => $actorRole,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'metadata' => $metadata === [] ? (object) [] : $metadata,
            'ip_address' => $runningInConsole ? null : request()->ip(),
            'user_agent' => $runningInConsole
                ? null
                : Str::limit((string) request()->userAgent(), 500),
            'created_at' => now(),
        ]);
    }

    /**
     * Keep audit metadata small and free of secrets. Callers should pass
     * field names and allowed values, never full model dumps or free text.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function sanitize(array $metadata): array
    {
        $blocked = ['password', 'password_confirmation', 'token', 'remember_token', 'secret', 'notes'];

        return collect($metadata)
            ->reject(fn (mixed $value, string $key): bool => in_array(Str::lower($key), $blocked, true))
            ->map(function (mixed $value): mixed {
                if (is_string($value)) {
                    return Str::limit($value, 500);
                }

                if (is_array($value)) {
                    return collect($value)
                        ->take(50)
                        ->map(fn (mixed $item): mixed => is_string($item) ? Str::limit($item, 200) : $item)
                        ->all();
                }

                return $value;
            })
            ->all();
    }
}
