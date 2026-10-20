<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use App\Models\Concerns\EnforcesPlanLimits;
use App\Support\Plans\PlanResources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class WorkflowRun extends Model
{
    use BelongsToChurch;
    use EnforcesPlanLimits;

    protected $fillable = ['workflow_id', 'workflow_version_id', 'status', 'current_node_index', 'context', 'errors', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['current_node_index' => 'integer', 'context' => 'array', 'errors' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public static function planResource(): string
    {
        return PlanResources::EXECUTIONS_PER_MONTH;
    }

    protected static function booted(): void
    {
        static::updating(function (WorkflowRun $run): void {
            if ($run->isDirty(['workflow_id', 'workflow_version_id'])) {
                throw new LogicException('A workflow run cannot change its workflow or published version.');
            }
        });
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function nodeExecutions(): HasMany
    {
        return $this->hasMany(WorkflowNodeExecution::class);
    }
}
