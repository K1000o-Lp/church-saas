<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ChurchInvitation extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'church_id',
        'email',
        'role',
        'token_hash',
        'status',
        'invited_by',
        'accepted_by',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function newToken(): array
    {
        $plain = Str::random(64);

        return [$plain, hash('sha256', $plain)];
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->expires_at->isPast();
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function markExpired(): void
    {
        if ($this->status === self::STATUS_PENDING && $this->expires_at->isPast()) {
            $this->forceFill([
                'status' => self::STATUS_EXPIRED,
            ])->save();
        }
    }

    public function expiresAtLabel(): string
    {
        return Carbon::parse($this->expires_at)->translatedFormat('j \d\e F \d\e Y');
    }
}
