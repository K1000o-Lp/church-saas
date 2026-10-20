<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceItem extends Model
{
    use BelongsToChurch;

    protected $fillable = ['service_plan_id', 'ministry_team_id', 'title', 'position', 'notes'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function servicePlan(): BelongsTo
    {
        return $this->belongsTo(ServicePlan::class);
    }

    public function ministryTeam(): BelongsTo
    {
        return $this->belongsTo(MinistryTeam::class);
    }

    public function volunteerAssignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class);
    }
}
