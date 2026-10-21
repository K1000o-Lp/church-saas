<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Segment extends Model
{
    use BelongsToChurch;

    public const TYPE_DYNAMIC = 'dynamic';

    public const TYPE_STATIC = 'static';

    protected $fillable = ['name', 'description', 'type', 'rules', 'is_active'];

    protected function casts(): array
    {
        return ['rules' => 'array', 'is_active' => 'boolean'];
    }

    public function isDynamic(): bool
    {
        return $this->type === self::TYPE_DYNAMIC;
    }

    public function isStatic(): bool
    {
        return $this->type === self::TYPE_STATIC;
    }

    public function members(): HasMany
    {
        return $this->hasMany(SegmentMember::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_segments')
            ->withPivotValue('church_id', app(CurrentChurch::class)->id())
            ->withTimestamps();
    }
}
