<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Segment extends Model
{
    use BelongsToChurch;

    protected $fillable = ['name', 'description', 'rules', 'is_active'];

    protected function casts(): array
    {
        return ['rules' => 'array', 'is_active' => 'boolean'];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_segments')
            ->withPivotValue('church_id', app(CurrentChurch::class)->id())
            ->withTimestamps();
    }
}
