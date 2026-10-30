<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    use BelongsToChurch;

    protected $fillable = ['name', 'position', 'is_active'];

    protected function casts(): array
    {
        return ['position' => 'integer', 'is_active' => 'boolean'];
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(PastoralFollowUp::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(PersonPipelineProgress::class);
    }
}
