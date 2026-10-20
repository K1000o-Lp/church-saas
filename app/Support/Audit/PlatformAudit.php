<?php

namespace App\Support\Audit;

use App\Models\Church;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditEntry;
use Illuminate\Support\Str;

final class PlatformAudit
{
    public const LOGIN = 'platform.login';

    public const CHURCH_UPDATED = 'church.updated';

    public const CHURCH_STATUS_CHANGED = 'church.status_changed';

    public const CHURCH_SUBSCRIPTION_UPDATED = 'church.subscription_updated';

    public const CHURCH_LIMIT_OVERRIDES_UPDATED = 'church.limit_overrides_updated';

    public const CHURCH_FEATURE_OVERRIDES_UPDATED = 'church.feature_overrides_updated';

    public const CHURCH_PURGED = 'church.purged';

    public const INVOICE_ISSUED = 'invoice.issued';

    public const INVOICE_STATUS_CHANGED = 'invoice.status_changed';

    public const PAYMENT_RECORDED = 'payment.recorded';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function record(
        PlatformAdmin $admin,
        string $action,
        ?Church $church = null,
        array $metadata = [],
    ): PlatformAuditEntry {
        return self::write($admin->getKey(), $action, $church, $metadata);
    }

    /**
     * System actions (purge, console) have no platform admin actor but must
     * still leave an audit trail. The church snapshot is written before the
     * tenant row is deleted.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function recordSystem(
        string $action,
        ?Church $church = null,
        array $metadata = [],
    ): PlatformAuditEntry {
        return self::write(null, $action, $church, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private static function write(
        int|string|null $platformAdminId,
        string $action,
        ?Church $church,
        array $metadata,
    ): PlatformAuditEntry {
        $runningInConsole = app()->runningInConsole();

        if ($church !== null) {
            $metadata['church'] = [
                'name' => $church->name,
                'slug' => $church->slug,
            ];
        }

        return PlatformAuditEntry::create([
            'platform_admin_id' => $platformAdminId,
            'church_id' => $church?->getKey(),
            'action' => $action,
            'subject_type' => $church === null ? null : Church::class,
            'subject_id' => $church?->getKey(),
            'metadata' => $metadata === [] ? (object) [] : $metadata,
            'ip_address' => $runningInConsole ? null : request()->ip(),
            'user_agent' => $runningInConsole
                ? null
                : Str::limit((string) request()->userAgent(), 255),
        ]);
    }
}
