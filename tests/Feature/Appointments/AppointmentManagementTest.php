<?php

namespace Tests\Feature\Appointments;

use App\Models\Appointment;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\Person;
use App\Models\User;
use App\Support\Appointments\AppointmentStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppointmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_an_appointment(): void
    {
        $church = $this->createChurch('iglesia-citas-admin');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Ana', 'Pérez');

        $this->actingAs($admin)
            ->post(route('appointments.store', $church), [
                'person_id' => $person->getKey(),
                'host_user_id' => $admin->getKey(),
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'duration_minutes' => 60,
                'subject' => 'Consejería',
                'notes' => 'Nota privada.',
            ])
            ->assertRedirect();

        $appointment = Appointment::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame($person->getKey(), $appointment->person_id);
        $this->assertSame($admin->getKey(), $appointment->host_user_id);
        $this->assertSame(AppointmentStatus::Scheduled->value, $appointment->status);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'appointment.created',
            'subject_id' => $appointment->getKey(),
        ]);
    }

    public function test_the_appointments_screens_render_for_an_admin(): void
    {
        $church = $this->createChurch('iglesia-citas-ui');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Beto', 'Rojas');
        $appointment = $this->createAppointment($church, $person, $admin);

        $this->actingAs($admin)
            ->get(route('appointments.index', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('appointments/index')
                ->where('canManage', true)
                ->has('appointments', 1)
                ->where('appointments.0.id', $appointment->getKey())
                ->has('statuses', 5));

        $this->actingAs($admin)
            ->get(route('appointments.show', [
                'church' => $church,
                'appointment' => $appointment->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('appointments/show')
                ->where('appointment.id', $appointment->getKey())
                ->where('appointment.notes', 'Nota privada.')
                ->where('canViewNotes', true));
    }

    public function test_a_leader_only_sees_and_manages_their_own_appointments(): void
    {
        $church = $this->createChurch('iglesia-citas-lider');
        $leader = $this->createMember($church, 'leader');
        $otherLeader = $this->createMember($church, 'leader');
        $person = $this->createPerson($church, 'Carla', 'Díaz');

        $own = $this->createAppointment($church, $person, $leader);
        $foreign = $this->createAppointment($church, $person, $otherLeader);

        $this->actingAs($leader)
            ->get(route('appointments.index', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('appointments', 1)
                ->where('appointments.0.id', $own->getKey()));

        $this->actingAs($leader)
            ->get(route('appointments.show', [
                'church' => $church,
                'appointment' => $foreign->getKey(),
            ]))
            ->assertForbidden();
    }

    public function test_a_leader_cannot_assign_an_appointment_to_another_host(): void
    {
        $church = $this->createChurch('iglesia-citas-host-forzado');
        $leader = $this->createMember($church, 'leader');
        $pastor = $this->createMember($church, 'pastor');
        $person = $this->createPerson($church, 'Diego', 'Suárez');

        $this->actingAs($leader)
            ->post(route('appointments.store', $church), [
                'person_id' => $person->getKey(),
                'host_user_id' => $pastor->getKey(),
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'duration_minutes' => 45,
            ])
            ->assertRedirect();

        $appointment = Appointment::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame($leader->getKey(), $appointment->host_user_id);
    }

    public function test_a_pastor_can_view_notes_of_any_appointment_in_the_church(): void
    {
        $church = $this->createChurch('iglesia-citas-pastor');
        $pastor = $this->createMember($church, 'pastor');
        $leader = $this->createMember($church, 'leader');
        $person = $this->createPerson($church, 'Elena', 'Mora');
        $appointment = $this->createAppointment($church, $person, $leader, 'Nota del líder.');

        $this->actingAs($pastor)
            ->get(route('appointments.show', [
                'church' => $church,
                'appointment' => $appointment->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('appointment.notes', 'Nota del líder.')
                ->where('canViewNotes', true));
    }

    public function test_appointment_actions_update_status_and_are_audited(): void
    {
        $church = $this->createChurch('iglesia-citas-acciones');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Fabián', 'Gil');

        $appointment = $this->createAppointment($church, $person, $admin);

        $this->actingAs($admin)
            ->patch(route('appointments.complete', [
                'church' => $church,
                'appointment' => $appointment->getKey(),
            ]))
            ->assertRedirect();

        $appointment->refresh();
        $this->assertSame(AppointmentStatus::Completed->value, $appointment->status);
        $this->assertNotNull($appointment->completed_at);

        $this->actingAs($admin)
            ->patch(route('appointments.cancel', [
                'church' => $church,
                'appointment' => $appointment->getKey(),
            ]), ['reason' => 'Ya no aplica.'])
            ->assertSessionHasErrors('status');

        $second = $this->createAppointment($church, $person, $admin);

        $this->actingAs($admin)
            ->patch(route('appointments.reschedule', [
                'church' => $church,
                'appointment' => $second->getKey(),
            ]), [
                'scheduled_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'duration_minutes' => 90,
            ])
            ->assertRedirect();

        $second->refresh();
        $this->assertSame(AppointmentStatus::Rescheduled->value, $second->status);
        $this->assertSame(90, $second->duration_minutes);

        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'appointment.completed',
        ]);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'appointment.rescheduled',
        ]);
    }

    public function test_appointment_validation_rejects_invalid_inputs(): void
    {
        $church = $this->createChurch('iglesia-citas-validacion');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Gina', 'López');
        $otherChurch = $this->createChurch('iglesia-citas-otra');
        $foreignPerson = $this->createPerson($otherChurch, 'Hugo', 'Ramos');

        $this->actingAs($admin)
            ->post(route('appointments.store', $church), [
                'person_id' => $foreignPerson->getKey(),
                'host_user_id' => $admin->getKey(),
                'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'duration_minutes' => 60,
            ])
            ->assertSessionHasErrors('person_id');

        $this->actingAs($admin)
            ->post(route('appointments.store', $church), [
                'person_id' => $person->getKey(),
                'host_user_id' => $admin->getKey(),
                'scheduled_at' => now()->subDay()->format('Y-m-d H:i:s'),
                'duration_minutes' => 10,
            ])
            ->assertSessionHasErrors(['scheduled_at', 'duration_minutes']);
    }

    public function test_appointments_are_isolated_between_churches(): void
    {
        $alpha = $this->createChurch('iglesia-citas-alpha');
        $alphaAdmin = $this->createMember($alpha, 'admin');
        $alphaPerson = $this->createPerson($alpha, 'Irene', 'Vega');
        $appointment = $this->createAppointment($alpha, $alphaPerson, $alphaAdmin);

        $beta = $this->createChurch('iglesia-citas-beta');
        $betaAdmin = $this->createMember($beta, 'admin');

        $this->actingAs($betaAdmin)
            ->get(route('appointments.show', [
                'church' => $beta,
                'appointment' => $appointment->getKey(),
            ]))
            ->assertNotFound();
    }

    public function test_a_member_without_the_permission_cannot_access_appointments(): void
    {
        $church = $this->createChurch('iglesia-citas-miembro');
        $member = $this->createMember($church, 'member');

        $this->actingAs($member)
            ->get(route('appointments.index', $church))
            ->assertForbidden();
    }

    private function createAppointment(
        Church $church,
        Person $person,
        User $host,
        string $notes = 'Nota privada.',
    ): Appointment {
        return app(CurrentChurch::class)->run($church, fn (): Appointment => Appointment::create([
            'person_id' => $person->getKey(),
            'host_user_id' => $host->getKey(),
            'created_by' => $host->getKey(),
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
            'status' => AppointmentStatus::Scheduled->value,
            'subject' => 'Seguimiento',
            'notes' => $notes,
        ]));
    }

    private function createPerson(Church $church, string $firstName, string $lastName): Person
    {
        return app(CurrentChurch::class)->run($church, fn (): Person => Person::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => 'active',
            'custom_fields' => (object) [],
        ]));
    }

    private function createChurch(string $slug): Church
    {
        return Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'active',
            'country_code' => 'VE',
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
            'locale' => 'es',
        ]);
    }

    private function createMember(Church $church, string $role): User
    {
        $user = User::factory()->create();

        ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role,
            'status' => 'active',
        ]);

        return $user;
    }
}
