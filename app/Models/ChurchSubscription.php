<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchSubscription extends Model
{
    protected $fillable = [
        'church_id',
        'plan_id',
        'status',
        'started_at',
        'plan_changed_at',
        'ends_at',
        'notes',
        'assigned_by_platform_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'plan_changed_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'assigned_by_platform_admin_id');
    }
}
