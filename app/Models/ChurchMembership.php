<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Global access-control row: it must remain queryable before tenant context exists. */
class ChurchMembership extends Model
{
    protected $fillable = ['church_id', 'user_id', 'role', 'status'];

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
