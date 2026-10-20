<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Person extends Model
{
    use BelongsToChurch;

    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'status', 'custom_fields'];

    protected function casts(): array
    {
        return ['custom_fields' => 'array'];
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function customFieldDefinitions(): HasMany
    {
        return $this->hasMany(CustomFieldDefinition::class, 'church_id', 'church_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function pastoralFollowUps(): HasMany
    {
        return $this->hasMany(PastoralFollowUp::class);
    }
}
