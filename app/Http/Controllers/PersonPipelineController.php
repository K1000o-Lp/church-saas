<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Person;
use App\Models\PersonPipelineProgress;
use App\Models\PipelineStage;
use App\Support\Audit\DomainAudit;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;

class PersonPipelineController extends Controller
{
    public function complete(Church $church, string $person, string $stage): RedirectResponse
    {
        $personRecord = Person::query()->findOrFail($person);
        $stageRecord = PipelineStage::query()->findOrFail($stage);

        $progress = PersonPipelineProgress::query()->firstOrCreate(
            [
                'person_id' => $personRecord->getKey(),
                'pipeline_stage_id' => $stageRecord->getKey(),
            ],
            ['status' => 'in_progress'],
        );

        $progress->forceFill([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $this->currentUserId(),
        ])->save();

        DomainAudit::record(DomainAudit::PIPELINE_STAGE_COMPLETED, $progress, [
            'personId' => $personRecord->getKey(),
            'stageId' => $stageRecord->getKey(),
        ]);

        return back()->with('success', 'Etapa marcada como completada.');
    }

    public function reopen(Church $church, string $person, string $stage): RedirectResponse
    {
        $personRecord = Person::query()->findOrFail($person);
        $stageRecord = PipelineStage::query()->findOrFail($stage);

        $progress = PersonPipelineProgress::query()
            ->where('person_id', $personRecord->getKey())
            ->where('pipeline_stage_id', $stageRecord->getKey())
            ->first();

        if ($progress === null) {
            return back();
        }

        $progress->forceFill([
            'status' => 'in_progress',
            'completed_at' => null,
            'completed_by' => null,
        ])->save();

        DomainAudit::record(DomainAudit::PIPELINE_STAGE_REOPENED, $progress, [
            'personId' => $personRecord->getKey(),
            'stageId' => $stageRecord->getKey(),
        ]);

        return back()->with('success', 'Etapa reabierta.');
    }

    private function currentUserId(): ?int
    {
        $userId = app(CurrentChurch::class)->membership()?->user_id;

        return $userId === null ? null : (int) $userId;
    }
}
