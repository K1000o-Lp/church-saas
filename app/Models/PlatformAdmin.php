<?php

namespace App\Models;

use Database\Factories\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PlatformAdmin extends Authenticatable
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'password', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function auditEntries(): HasMany
    {
        return $this->hasMany(PlatformAuditEntry::class);
    }
}
