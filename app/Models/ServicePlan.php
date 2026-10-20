<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePlan extends Model
{
    use BelongsToChurch;

    protected $fillable = ['title', 'starts_at', 'ends_at', 'location', 'status'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceItem::class)->orderBy('position');
    }
}
