<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\EnforcesPlanLimits;
use App\Support\Plans\PlanResources;
use Illuminate\Database\Eloquent\Model;

class ChurchCurrency extends Model
{
    use BelongsToChurch;
    use EnforcesPlanLimits;

    protected $fillable = ['currency_code', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function planResource(): string
    {
        return PlanResources::CURRENCIES;
    }
}
