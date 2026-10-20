<?php

namespace App\Models;

use App\Models\Concerns\BelongsToChurch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowNodeExecution extends Model
{
    use BelongsToChurch;

    protected $fillable = ['workflow_run_id', 'node_id', 'attempts', 'idempotency_key', 'status', 'input', 'output', 'error', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'input' => 'array', 'output' => 'array', 'error' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }
}
