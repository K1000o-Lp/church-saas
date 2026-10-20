<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomFieldDefinitionRequest;
use App\Http\Requests\UpdateCustomFieldDefinitionRequest;
use App\Models\Church;
use App\Models\CustomFieldDefinition;
use App\Support\Audit\DomainAudit;
use App\Support\People\CustomFieldRegistry;
use App\Support\People\CustomFieldType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomFieldDefinitionController extends Controller
{
    public function index(Church $church): Response
    {
        Gate::authorize('viewAny', CustomFieldDefinition::class);

        return Inertia::render('settings/custom-fields', [
            'church' => [
                'name' => $church->name,
                'slug' => $church->slug,
            ],
            'definitions' => CustomFieldRegistry::allFor($church)
                ->map(fn (CustomFieldDefinition $definition): array => $this->present($definition))
                ->values()
                ->all(),
            'types' => CustomFieldType::options(),
        ]);
    }

    public function store(
        StoreCustomFieldDefinitionRequest $request,
        Church $church,
    ): RedirectResponse {
        Gate::authorize('create', CustomFieldDefinition::class);

        $data = $request->validated();
        $type = CustomFieldType::from($data['type']);

        $definition = CustomFieldDefinition::create([
            'key' => $data['key'],
            'label' => $data['label'],
            'type' => $data['type'],
            'options' => $type->requiresOptions()
                ? CustomFieldRegistry::optionChoices($data['options'] ?? [])
                : null,
            'is_required' => (bool) ($data['is_required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        DomainAudit::record(DomainAudit::CUSTOM_FIELD_CREATED, $definition, [
            'key' => $definition->key,
            'type' => $definition->type,
        ]);

        return back()->with('success', 'Campo personalizado creado.');
    }

    public function update(
        UpdateCustomFieldDefinitionRequest $request,
        Church $church,
        string $definition,
    ): RedirectResponse {
        $record = $this->findDefinition($definition);

        Gate::authorize('update', $record);

        $data = $request->validated();
        $type = CustomFieldType::tryFrom((string) $record->type);

        $record->fill([
            'label' => $data['label'],
            'is_required' => (bool) ($data['is_required'] ?? $record->is_required),
            'is_active' => (bool) ($data['is_active'] ?? $record->is_active),
        ]);

        if ($request->has('options')) {
            $record->options = $type?->requiresOptions()
                ? CustomFieldRegistry::optionChoices($data['options'] ?? [])
                : null;
        }

        $changed = array_keys($record->getDirty());
        $record->save();

        DomainAudit::record(DomainAudit::CUSTOM_FIELD_UPDATED, $record, [
            'key' => $record->key,
            'changed' => $changed,
            'isActive' => $record->is_active,
        ]);

        return back()->with('success', 'Campo personalizado actualizado.');
    }

    public function toggle(Church $church, string $definition): RedirectResponse
    {
        $record = $this->findDefinition($definition);

        Gate::authorize('update', $record);

        $record->update(['is_active' => ! $record->is_active]);

        DomainAudit::record(
            $record->is_active
                ? DomainAudit::CUSTOM_FIELD_UPDATED
                : DomainAudit::CUSTOM_FIELD_DEACTIVATED,
            $record,
            ['key' => $record->key, 'isActive' => $record->is_active],
        );

        return back()->with(
            'success',
            $record->is_active ? 'Campo activado.' : 'Campo desactivado.',
        );
    }

    private function findDefinition(string $definition): CustomFieldDefinition
    {
        return CustomFieldDefinition::query()->findOrFail($definition);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(CustomFieldDefinition $definition): array
    {
        return [
            'id' => $definition->getKey(),
            'key' => $definition->key,
            'label' => $definition->label,
            'type' => $definition->type,
            'typeLabel' => CustomFieldType::tryFrom((string) $definition->type)?->label() ?? $definition->type,
            'options' => CustomFieldRegistry::optionChoices($definition->options),
            'isRequired' => $definition->is_required,
            'isActive' => $definition->is_active,
        ];
    }
}
