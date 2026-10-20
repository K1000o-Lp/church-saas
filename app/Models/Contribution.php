<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    use BelongsToChurch;

    protected $fillable = ['person_id', 'fund_id', 'amount_minor', 'currency_code', 'method', 'status', 'contributed_at', 'reference'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'contributed_at' => 'datetime'];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }
}
