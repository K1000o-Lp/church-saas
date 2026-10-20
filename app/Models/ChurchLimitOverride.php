<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChurchLimitOverride extends Model
{
    protected $fillable = [
        'church_id',
        'resource',
        'limit_value',
        'note',
        'created_by_platform_admin_id',
    ];

    protected function casts(): array
    {
        return ['limit_value' => 'integer'];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'created_by_platform_admin_id');
    }
}
