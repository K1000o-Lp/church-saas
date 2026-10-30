<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePipelineStageRequest;
use App\Http\Requests\UpdatePipelineStageRequest;
use App\Models\Church;
use App\Models\PipelineStage;
use App\Support\Audit\DomainAudit;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PipelineStageController extends Controller
{
    public function index(Church $church): Response
    {
        return Inertia::render('settings/pipeline', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'stages' => PipelineStage::query()
                ->withCount([
                    'progress as completed_count' => fn ($query) => $query->where('status', 'completed'),
                ])
                ->orderBy('position')
                ->orderBy('name')
                ->get()
                ->map(fn (PipelineStage $stage): array => [
                    'id' => $stage->getKey(),
                    'name' => $stage->name,
                    'position' => $stage->position,
                    'isActive' => $stage->is_active,
                    'completedCount' => (int) ($stage->completed_count ?? 0),
                ])
                ->values(),
            'canManage' => app(CurrentChurch::class)->allows(Permission::PastoralManage),
        ]);
    }

    public function store(StorePipelineStageRequest $request, Church $church): RedirectResponse
    {
        $data = $request->validated();

        $stage = PipelineStage::create([
            'name' => $data['name'],
            'position' => $data['position'],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        DomainAudit::record(DomainAudit::PIPELINE_STAGE_CREATED, $stage, [
            'name' => $stage->name,
            'position' => $stage->position,
        ]);

        return back()->with('success', 'Etapa creada.');
    }

    public function update(
        UpdatePipelineStageRequest $request,
        Church $church,
        string $stage,
    ): RedirectResponse {
        $record = PipelineStage::query()->findOrFail($stage);

        $data = $request->validated();

        $record->fill([
            'name' => $data['name'],
            'position' => $data['position'],
            'is_active' => (bool) ($data['is_active'] ?? $record->is_active),
        ])->save();

        DomainAudit::record(DomainAudit::PIPELINE_STAGE_UPDATED, $record, [
            'name' => $record->name,
            'position' => $record->position,
            'isActive' => $record->is_active,
        ]);

        return back()->with('success', 'Etapa actualizada.');
    }
}
