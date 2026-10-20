<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    use BelongsToChurch;

    protected $fillable = ['name', 'status'];

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }
}
