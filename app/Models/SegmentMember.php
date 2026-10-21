<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SegmentMember extends Model
{
    use BelongsToChurch;

    protected $fillable = ['segment_id', 'person_id', 'added_by'];

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
