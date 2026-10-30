<?php

namespace App\Models;

use App\Support\Billing\InvoiceStatus;
use App\Support\Billing\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Platform-owned billing record. It is queried explicitly by church in controllers. */
class Invoice extends Model
{
    protected $fillable = [
        'church_id',
        'church_name',
        'church_slug',
        'number',
        'status',
        'currency_code',
        'amount',
        'description',
        'notes',
        'issued_at',
        'due_at',
        'paid_at',
        'voided_at',
        'void_reason',
        'issued_by_platform_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'issued_by_platform_admin_id');
    }

    public function paidAmount(): string
    {
        $amounts = $this->relationLoaded('payments')
            ? $this->payments->pluck('amount')->all()
            : $this->payments()->pluck('amount')->all();

        return Money::add(...$amounts);
    }

    public function remainingAmount(): string
    {
        return Money::maxZero(Money::subtract($this->amount, $this->paidAmount()));
    }

    public function isFullyPaid(): bool
    {
        return Money::compare($this->paidAmount(), $this->amount) >= 0;
    }

    public function scopeForChurch(Builder $query, Church|int $church): Builder
    {
        return $query->where('church_id', $church instanceof Church ? $church->getKey() : $church);
    }

    /**
     * Persist the automatic issued -> overdue transition. This keeps the
     * stored state consistent with the due date without a scheduler.
     */
    public static function markOverdue(?Church $church = null): int
    {
        $query = static::query()
            ->where('status', InvoiceStatus::ISSUED)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now());

        if ($church !== null) {
            $query->forChurch($church);
        }

        return $query->update([
            'status' => InvoiceStatus::OVERDUE,
            'updated_at' => now(),
        ]);
    }

    public function money(): string
    {
        return Money::format($this->amount);
    }
}
