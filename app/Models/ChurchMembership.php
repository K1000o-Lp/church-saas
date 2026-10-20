<?php

namespace App\Models;

use App\Models\Concerns\EnforcesPlanLimits;
use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanEnforcement;
use App\Support\Plans\PlanResources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Global access-control row: it must remain queryable before tenant context exists. */
class ChurchMembership extends Model
{
    use EnforcesPlanLimits;

    protected $fillable = ['church_id', 'user_id', 'role', 'status'];

    protected static function booted(): void
    {
        static::updating(function (ChurchMembership $membership): void {
            if (PlanEnforcement::isBypassed()) {
                return;
            }

            if (! $membership->isDirty('status') || $membership->status !== 'active') {
                return;
            }

            Entitlements::forChurchId((int) $membership->church_id)
                ->ensureCanCreate(PlanResources::ACTIVE_USERS);
        });
    }

    public static function planResource(): string
    {
        return PlanResources::ACTIVE_USERS;
    }

    public function shouldEnforcePlanLimit(): bool
    {
        return $this->status === 'active';
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
