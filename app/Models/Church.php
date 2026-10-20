<?php

namespace App\Models;

use App\Support\Plans\PlanMatrix;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Global tenant registry; intentionally not scoped by church_id. */
class Church extends Model
{
    protected static function booted(): void
    {
        static::created(function (Church $church): void {
            PlanMatrix::assignDefaultTo($church->getKey());
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'country_code',
        'timezone',
        'currency_code',
        'locale',
        'settings',
        'status',
        'status_reason',
        'status_changed_at',
        'status_changed_by_platform_admin_id',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'status_changed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ChurchMembership::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(ChurchSubscription::class);
    }

    public function limitOverrides(): HasMany
    {
        return $this->hasMany(ChurchLimitOverride::class);
    }

    public function featureOverrides(): HasMany
    {
        return $this->hasMany(ChurchFeatureOverride::class);
    }

    public function statusChangedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'status_changed_by_platform_admin_id');
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(PlatformAuditEntry::class, 'church_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function currencies(): HasMany
    {
        return $this->hasMany(ChurchCurrency::class);
    }

    public function funds(): HasMany
    {
        return $this->hasMany(Fund::class);
    }

    public function ministryTeams(): HasMany
    {
        return $this->hasMany(MinistryTeam::class);
    }

    public function servicePlans(): HasMany
    {
        return $this->hasMany(ServicePlan::class);
    }

    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }
}
