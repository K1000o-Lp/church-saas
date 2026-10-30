<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'price',
        'currency_code',
        'billing_interval',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function limits(): HasMany
    {
        return $this->hasMany(PlanLimit::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(ChurchSubscription::class);
    }

    public function limitFor(string $resource): ?PlanLimit
    {
        return $this->limits->firstWhere('resource', $resource);
    }

    public function hasFeature(string $feature): bool
    {
        return $this->features->contains('feature', $feature);
    }
}
