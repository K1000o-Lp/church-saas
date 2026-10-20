<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\EnforcesPlanLimits;
use App\Support\Plans\PlanResources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workflow extends Model
{
    use BelongsToChurch;
    use EnforcesPlanLimits;

    protected $fillable = ['name', 'trigger_type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function planResource(): string
    {
        return PlanResources::WORKFLOWS;
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }
}
