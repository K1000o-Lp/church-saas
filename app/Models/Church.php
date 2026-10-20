<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Global tenant registry; intentionally not scoped by church_id. */
class Church extends Model
{
    protected $fillable = ['name', 'slug', 'country_code', 'timezone', 'currency_code', 'locale', 'settings', 'status'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ChurchMembership::class);
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
