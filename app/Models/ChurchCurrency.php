<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;

class ChurchCurrency extends Model
{
    use BelongsToChurch;

    protected $fillable = ['currency_code', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
