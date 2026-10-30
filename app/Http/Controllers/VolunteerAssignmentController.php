<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVolunteerAssignmentRequest;
use App\Http\Requests\UpdateVolunteerAssignmentStatusRequest;
use App\Models\Church;
use App\Models\ServicePlan;
use App\Models\VolunteerAssignment;
use App\Support\Audit\DomainAudit;
use App\Support\Services\AssignmentStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class VolunteerAssignmentController extends Controller
{
    public function store(
        StoreVolunteerAssignmentRequest $request,
        Church $church,
        string $service,
        string $item,
    ): RedirectResponse {
        $plan = ServicePlan::query()->findOrFail($service);
        $serviceItem = $plan->items()->findOrFail($item);
        $data = $request->validated();

        $status = $data['status'];

        $existing = $serviceItem->volunteerAssignments()
            ->where('person_id', $data['person_id'])
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'person_id' => 'La persona ya está asignada a este ítem.',
            ]);
        }

        $assignment = VolunteerAssignment::create([
            'service_item_id' => $serviceItem->getKey(),
            'person_id' => $data['person_id'],
            'role' => $data['role'] ?? null,
            'status' => $status,
            'confirmed_at' => $status === AssignmentStatus::Confirmed->value ? now() : null,
        ]);

        DomainAudit::record(DomainAudit::VOLUNTEER_ASSIGNED, $assignment, [
            'serviceId' => $plan->getKey(),
            'itemId' => $serviceItem->getKey(),
            'personId' => $assignment->person_id,
            'status' => $assignment->status,
        ]);

        return back()->with('success', 'Voluntario asignado.');
    }

    public function updateStatus(
        UpdateVolunteerAssignmentStatusRequest $request,
        Church $church,
        string $service,
        string $item,
        string $assignment,
    ): RedirectResponse {
        $plan = ServicePlan::query()->findOrFail($service);
        $serviceItem = $plan->items()->findOrFail($item);
        $record = $serviceItem->volunteerAssignments()->findOrFail($assignment);
        $data = $request->validated();

        $record->forceFill([
            'status' => $data['status'],
            'confirmed_at' => $data['status'] === AssignmentStatus::Confirmed->value
                ? ($record->confirmed_at ?? now())
                : null,
        ])->save();

        DomainAudit::record(DomainAudit::VOLUNTEER_STATUS_CHANGED, $record, [
            'serviceId' => $plan->getKey(),
            'itemId' => $serviceItem->getKey(),
            'personId' => $record->person_id,
            'status' => $record->status,
        ]);

        return back()->with('success', 'Estado de la asignación actualizado.');
    }

    public function destroy(
        Church $church,
        string $service,
        string $item,
        string $assignment,
    ): RedirectResponse {
        $plan = ServicePlan::query()->findOrFail($service);
        $serviceItem = $plan->items()->findOrFail($item);
        $record = $serviceItem->volunteerAssignments()->findOrFail($assignment);

        DomainAudit::record(DomainAudit::VOLUNTEER_STATUS_CHANGED, $record, [
            'serviceId' => $plan->getKey(),
            'itemId' => $serviceItem->getKey(),
            'personId' => $record->person_id,
            'status' => 'removed',
        ]);

        $record->delete();

        return back()->with('success', 'Asignación eliminada.');
    }
}
