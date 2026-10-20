<?php

namespace App\Models\Concerns;

use App\Support\Plans\Entitlements;
use App\Support\Plans\PlanEnforcement;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Database\Eloquent\Model;

trait EnforcesPlanLimits
{
    protected static function bootEnforcesPlanLimits(): void
    {
        static::creating(function (Model $model): void {
            if (PlanEnforcement::isBypassed()) {
                return;
            }

            if (! $model->shouldEnforcePlanLimit()) {
                return;
            }

            $churchId = $model->getAttribute('church_id') ?? app(CurrentChurch::class)->id();

            if ($churchId === null) {
                // BelongsToChurch reports missing or mismatched tenant context.
                return;
            }

            $resource = static::planResource();

            Entitlements::forChurchId((int) $churchId)->ensureCanCreate($resource);
        });
    }

    public function shouldEnforcePlanLimit(): bool
    {
        return true;
    }

    abstract public static function planResource(): string;
}
