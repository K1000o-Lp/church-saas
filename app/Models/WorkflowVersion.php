<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class WorkflowVersion extends Model
{
    use BelongsToChurch;

    protected $fillable = ['workflow_id', 'version', 'definition'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'definition' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Published workflow versions are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Published workflow versions cannot be deleted individually.');
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }
}
