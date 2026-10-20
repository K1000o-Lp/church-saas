<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use BelongsToChurch;

    protected $fillable = ['title', 'description', 'location', 'starts_at', 'ends_at', 'timezone', 'status'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
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
}
