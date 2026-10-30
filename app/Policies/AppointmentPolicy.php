<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Auth\Permission;
use App\Support\Tenancy\CurrentChurch;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows(Permission::AppointmentsView);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->allows(Permission::AppointmentsView)
            && $this->sameChurch($appointment)
            && $this->isManagerOrHost($user, $appointment);
    }

    public function create(User $user): bool
    {
        return $this->allows(Permission::AppointmentsManage);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->allows(Permission::AppointmentsManage)
            && $this->sameChurch($appointment)
            && $this->isManagerOrHost($user, $appointment);
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }

    public function noShow(User $user, Appointment $appointment): bool
    {
        return $this->update($user, $appointment);
    }

    public function viewNotes(User $user, Appointment $appointment): bool
    {
        return $this->allows(Permission::AppointmentsNotesView)
            && $this->sameChurch($appointment)
            && $this->isManagerOrHost($user, $appointment);
    }

    private function allows(Permission $permission): bool
    {
        return app(CurrentChurch::class)->allows($permission);
    }

    private function sameChurch(Appointment $appointment): bool
    {
        return (string) app(CurrentChurch::class)->id() === (string) $appointment->church_id;
    }

    private function isManagerOrHost(User $user, Appointment $appointment): bool
    {
        $currentChurch = app(CurrentChurch::class);

        if (in_array($currentChurch->role(), ['admin', 'pastor'], true)) {
            return true;
        }

        $membership = $currentChurch->membership();

        return $membership !== null
            && (string) $membership->user_id === (string) $user->getAuthIdentifier()
            && (string) $appointment->host_user_id === (string) $user->getAuthIdentifier();
    }
}
