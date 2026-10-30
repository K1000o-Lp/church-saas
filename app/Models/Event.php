<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\EnforcesPlanLimits;
use App\Support\Events\RegistrationStatus;
use App\Support\Plans\PlanResources;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use BelongsToChurch;
    use EnforcesPlanLimits;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'location',
        'starts_at',
        'ends_at',
        'timezone',
        'status',
        'capacity',
        'is_public',
        'is_paid',
        'price',
        'currency_code',
        'registration_opens_at',
        'registration_closes_at',
        'cover_image_path',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'capacity' => 'integer',
            'is_public' => 'boolean',
            'is_paid' => 'boolean',
            'price' => 'decimal:2',
        ];
    }

    public static function planResource(): string
    {
        return PlanResources::EVENTS_PER_MONTH;
    }

    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class, 'event_segments')
            ->withPivotValue('church_id', app(CurrentChurch::class)->id())
            ->withTimestamps();
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function occupiedSeats(): int
    {
        return $this->registrations()
            ->whereIn('status', [
                RegistrationStatus::Registered->value,
                RegistrationStatus::Confirmed->value,
            ])
            ->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->occupiedSeats() >= $this->capacity;
    }

    public function registrationIsOpen(): bool
    {
        if (in_array($this->status, ['cancelled', 'completed'], true)) {
            return false;
        }

        $now = now();

        if ($this->registration_opens_at !== null && $now->lt($this->registration_opens_at)) {
            return false;
        }

        return ! ($this->registration_closes_at !== null && $now->gt($this->registration_closes_at));
    }
}
