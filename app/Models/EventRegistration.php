<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRegistration extends Model
{
    use BelongsToChurch;

    protected $fillable = [
        'event_id',
        'person_id',
        'guest_name',
        'status',
        'payment_status',
        'guest_count',
        'responded_at',
        'public_token_hash',
        'ticket_code_hash',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'responded_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class, 'event_registration_id');
    }
}
