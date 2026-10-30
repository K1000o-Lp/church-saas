<?php

namespace App\Models;

use App\Support\Billing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manual payment recorded by a platform administrator. */
class Payment extends Model
{
    protected $fillable = [
        'invoice_id',
        'church_id',
        'amount',
        'currency_code',
        'method',
        'reference',
        'notes',
        'paid_at',
        'recorded_by_platform_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'recorded_by_platform_admin_id');
    }

    public function money(): string
    {
        return Money::format($this->amount);
    }
}
