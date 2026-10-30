<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceItemRequest;
use App\Http\Requests\UpdateServiceItemRequest;
use App\Models\Church;
use App\Models\ServiceItem;
use App\Models\ServicePlan;
use App\Support\Audit\DomainAudit;
use Illuminate\Http\RedirectResponse;

class ServiceItemController extends Controller
{
    public function store(
        StoreServiceItemRequest $request,
        Church $church,
        string $service,
    ): RedirectResponse {
        $plan = ServicePlan::query()->findOrFail($service);
        $data = $request->validated();

        $item = ServiceItem::create([
            'service_plan_id' => $plan->getKey(),
            'ministry_team_id' => $data['ministry_team_id'] ?? null,
            'title' => $data['title'],
            'position' => $data['position'],
            'notes' => $data['notes'] ?? null,
        ]);

        DomainAudit::record(DomainAudit::SERVICE_ITEM_CREATED, $item, [
            'serviceId' => $plan->getKey(),
            'title' => $item->title,
            'position' => $item->position,
        ]);

        return back()->with('success', 'Ítem agregado.');
    }

    public function update(
        UpdateServiceItemRequest $request,
        Church $church,
        string $service,
        string $item,
    ): RedirectResponse {
        $plan = ServicePlan::query()->findOrFail($service);
        $record = $plan->items()->findOrFail($item);
        $data = $request->validated();

        $record->fill([
            'ministry_team_id' => $data['ministry_team_id'] ?? null,
            'title' => $data['title'],
            'position' => $data['position'],
            'notes' => $data['notes'] ?? null,
        ])->save();

        DomainAudit::record(DomainAudit::SERVICE_ITEM_UPDATED, $record, [
            'serviceId' => $plan->getKey(),
            'title' => $record->title,
            'position' => $record->position,
        ]);

        return back()->with('success', 'Ítem actualizado.');
    }

    public function destroy(Church $church, string $service, string $item): RedirectResponse
    {
        $plan = ServicePlan::query()->findOrFail($service);
        $record = $plan->items()->findOrFail($item);

        DomainAudit::record(DomainAudit::SERVICE_ITEM_DELETED, $record, [
            'serviceId' => $plan->getKey(),
            'title' => $record->title,
        ]);

        $record->delete();

        return back()->with('success', 'Ítem eliminado.');
    }
}
