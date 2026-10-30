<?php

namespace Tests\Feature\Events;

use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Person;
use App\Models\User;
use App\Support\Events\EventStatus;
use App\Support\Events\PaymentStatus;
use App\Support\Events\RegistrationStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_create_an_event(): void
    {
        $church = $this->createChurch('iglesia-eventos-crear');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->post(route('events.store', $church), [
                'title' => 'Retiro de jóvenes',
                'description' => 'Un fin de semana de encuentro.',
                'location' => 'Casa de retiros',
                'starts_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
                'ends_at' => now()->addDays(11)->format('Y-m-d H:i:s'),
                'status' => 'draft',
                'capacity' => 40,
                'is_public' => true,
                'is_paid' => false,
            ])
            ->assertRedirect();

        $event = Event::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame('Retiro de jóvenes', $event->title);
        $this->assertSame('retiro-de-jovenes', $event->slug);
        $this->assertSame(40, $event->capacity);
        $this->assertTrue($event->is_public);
        $this->assertFalse($event->is_paid);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'event.created',
            'subject_id' => $event->getKey(),
        ]);
    }

    public function test_paid_events_require_a_valid_price_and_currency(): void
    {
        $church = $this->createChurch('iglesia-eventos-pago');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->post(route('events.store', $church), [
                'title' => 'Concierto',
                'status' => 'draft',
                'is_paid' => true,
            ])
            ->assertSessionHasErrors(['price', 'currency_code']);

        $this->actingAs($admin)
            ->post(route('events.store', $church), [
                'title' => 'Concierto',
                'status' => 'draft',
                'is_paid' => true,
                'price' => '10.00',
                'currency_code' => 'EUR',
            ])
            ->assertSessionHasErrors('currency_code');
    }

    public function test_event_status_actions_are_audited(): void
    {
        $church = $this->createChurch('iglesia-eventos-estados');
        $admin = $this->createMember($church, 'admin');
        $event = $this->createEvent($church, ['starts_at' => now()->addDays(3)]);

        $this->actingAs($admin)
            ->patch(route('events.publish', ['church' => $church, 'event' => $event->getKey()]))
            ->assertRedirect();

        $event->refresh();
        $this->assertSame(EventStatus::Published->value, $event->status);

        $this->actingAs($admin)
            ->patch(route('events.complete', ['church' => $church, 'event' => $event->getKey()]))
            ->assertRedirect();

        $this->assertSame(EventStatus::Completed->value, $event->refresh()->status);

        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'event.published',
        ]);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'event.completed',
        ]);
    }

    public function test_a_free_registration_is_confirmed_and_does_not_require_payment(): void
    {
        $church = $this->createChurch('iglesia-eventos-gratis');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Ana', 'Pérez');
        $event = $this->createEvent($church);

        $this->actingAs($admin)
            ->post(route('events.registrations.store', [
                'church' => $church,
                'event' => $event->getKey(),
            ]), [
                'person_id' => $person->getKey(),
                'guest_count' => 1,
                'status' => 'registered',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $registration = EventRegistration::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame(RegistrationStatus::Confirmed->value, $registration->status);
        $this->assertSame(PaymentStatus::NotRequired->value, $registration->payment_status);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'registration.created',
        ]);
    }

    public function test_a_paid_registration_starts_pending_payment(): void
    {
        $church = $this->createChurch('iglesia-eventos-pago-registro');
        $this->createCurrency($church, 'USD');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Beto', 'Rojas');
        $event = $this->createEvent($church, [
            'is_paid' => true,
            'price' => '25.00',
            'currency_code' => 'USD',
        ]);

        $this->actingAs($admin)
            ->post(route('events.registrations.store', [
                'church' => $church,
                'event' => $event->getKey(),
            ]), [
                'person_id' => $person->getKey(),
                'guest_count' => 0,
                'status' => 'registered',
            ])
            ->assertSessionHasNoErrors();

        $registration = EventRegistration::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame(RegistrationStatus::Registered->value, $registration->status);
        $this->assertSame(PaymentStatus::Pending->value, $registration->payment_status);
    }

    public function test_capacity_sends_extra_registrations_to_the_waitlist(): void
    {
        $church = $this->createChurch('iglesia-eventos-cupo');
        $admin = $this->createMember($church, 'admin');
        $first = $this->createPerson($church, 'Carla', 'Díaz');
        $second = $this->createPerson($church, 'Diego', 'Suárez');
        $event = $this->createEvent($church, ['capacity' => 1]);

        foreach ([$first, $second] as $person) {
            $this->actingAs($admin)
                ->post(route('events.registrations.store', [
                    'church' => $church,
                    'event' => $event->getKey(),
                ]), [
                    'person_id' => $person->getKey(),
                    'guest_count' => 0,
                    'status' => 'registered',
                ])
                ->assertSessionHasNoErrors();
        }

        $registrations = EventRegistration::withoutGlobalScope('church')
            ->orderBy('id')
            ->get();

        $this->assertSame(RegistrationStatus::Confirmed->value, $registrations[0]->status);
        $this->assertSame(RegistrationStatus::Waitlisted->value, $registrations[1]->status);
        $this->assertTrue(app(CurrentChurch::class)->run(
            $church,
            fn (): bool => Event::query()->findOrFail($event->getKey())->isFull(),
        ));
    }

    public function test_attendance_can_be_recorded_for_a_registration(): void
    {
        $church = $this->createChurch('iglesia-eventos-asistencia');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Elena', 'Mora');
        $event = $this->createEvent($church);
        $registration = $this->createRegistration($event, $person);

        $this->actingAs($admin)
            ->post(route('events.attendance.store', [
                'church' => $church,
                'event' => $event->getKey(),
            ]), [
                'event_registration_id' => $registration->getKey(),
                'status' => 'present',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendance_records', [
            'church_id' => $church->getKey(),
            'event_id' => $event->getKey(),
            'event_registration_id' => $registration->getKey(),
            'person_id' => $person->getKey(),
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'attendance.recorded',
        ]);
    }

    public function test_events_and_registrations_are_isolated_between_churches(): void
    {
        $alpha = $this->createChurch('iglesia-eventos-alpha');
        $alphaAdmin = $this->createMember($alpha, 'admin');
        $alphaPerson = $this->createPerson($alpha, 'Fabián', 'Gil');
        $event = $this->createEvent($alpha);
        $registration = $this->createRegistration($event, $alphaPerson);

        $beta = $this->createChurch('iglesia-eventos-beta');
        $betaAdmin = $this->createMember($beta, 'admin');

        $this->actingAs($betaAdmin)
            ->get(route('events.show', ['church' => $beta, 'event' => $event->getKey()]))
            ->assertNotFound();

        $this->actingAs($betaAdmin)
            ->patch(route('events.registrations.update', [
                'church' => $beta,
                'event' => $event->getKey(),
                'registration' => $registration->getKey(),
            ]), ['status' => 'cancelled'])
            ->assertNotFound();
    }

    public function test_a_volunteer_can_view_events_but_not_manage_them(): void
    {
        $church = $this->createChurch('iglesia-eventos-voluntario');
        $volunteer = $this->createMember($church, 'volunteer');

        $this->actingAs($volunteer)
            ->get(route('events.index', $church))
            ->assertOk();

        $this->actingAs($volunteer)
            ->get(route('events.create', $church))
            ->assertForbidden();
    }

    public function test_the_event_screens_render(): void
    {
        $church = $this->createChurch('iglesia-eventos-ui');
        $admin = $this->createMember($church, 'admin');
        $person = $this->createPerson($church, 'Gina', 'López');
        $event = $this->createEvent($church);
        $this->createRegistration($event, $person);

        $this->actingAs($admin)
            ->get(route('events.index', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/index')
                ->has('events', 1)
                ->where('canManage', true));

        $this->actingAs($admin)
            ->get(route('events.show', ['church' => $church, 'event' => $event->getKey()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/show')
                ->where('event.id', $event->getKey())
                ->where('event.registrationsCount', 1));

        $this->actingAs($admin)
            ->get(route('events.registrations.index', [
                'church' => $church,
                'event' => $event->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/registrations')
                ->has('registrations', 1)
                ->has('people', 1));

        $this->actingAs($admin)
            ->get(route('events.attendance.index', [
                'church' => $church,
                'event' => $event->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/attendance')
                ->has('registrations', 1)
                ->where('canRecord', true));
    }

    private function createEvent(Church $church, array $attributes = []): Event
    {
        return app(CurrentChurch::class)->run($church, fn (): Event => Event::create([
            'title' => $attributes['title'] ?? 'Culto especial',
            'slug' => $attributes['slug'] ?? 'culto-especial-'.uniqid(),
            'description' => $attributes['description'] ?? null,
            'location' => $attributes['location'] ?? 'Templo',
            'starts_at' => $attributes['starts_at'] ?? now()->addDays(5),
            'ends_at' => $attributes['ends_at'] ?? null,
            'timezone' => 'America/Caracas',
            'status' => $attributes['status'] ?? EventStatus::Published->value,
            'capacity' => $attributes['capacity'] ?? null,
            'is_public' => $attributes['is_public'] ?? true,
            'is_paid' => $attributes['is_paid'] ?? false,
            'price' => $attributes['price'] ?? null,
            'currency_code' => $attributes['currency_code'] ?? null,
        ]));
    }

    private function createRegistration(Event $event, Person $person): EventRegistration
    {
        return app(CurrentChurch::class)->run($event->church_id, fn (): EventRegistration => EventRegistration::create([
            'event_id' => $event->getKey(),
            'person_id' => $person->getKey(),
            'status' => RegistrationStatus::Confirmed->value,
            'payment_status' => PaymentStatus::NotRequired->value,
            'guest_count' => 0,
            'responded_at' => now(),
        ]));
    }

    private function createCurrency(Church $church, string $code): void
    {
        app(CurrentChurch::class)->run($church, fn (): ChurchCurrency => ChurchCurrency::create([
            'currency_code' => $code,
            'is_default' => true,
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
