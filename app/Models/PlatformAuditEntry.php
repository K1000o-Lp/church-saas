<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAuditEntry extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'platform_admin_id',
        'church_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }
}
