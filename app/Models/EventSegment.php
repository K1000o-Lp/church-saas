<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSegment extends Model
{
    use BelongsToChurch;

    protected $fillable = ['event_id', 'segment_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }
}
