<?php

namespace Tests\Feature\Events;

use App\Models\AttendanceRecord;
use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Support\Events\EventStatus;
use App\Support\Events\PaymentStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_free_public_registration_gets_a_ticket(): void
    {
        $church = $this->createChurch('tickets-gratis');
        $event = $this->createEvent($church);

        $response = $this->post(route('public.event.register', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]), [
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.com',
            'guest_count' => 0,
            'website' => '',
        ]);

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('/entradas/', $location);

        $registration = EventRegistration::withoutGlobalScope('church')->firstOrFail();
        $this->assertNotNull($registration->ticket_code_hash);
        $this->assertNotNull($registration->issued_at);

        $token = basename(parse_url($location, PHP_URL_PATH) ?? '');

        $this->get(route('public.ticket.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/ticket')
                ->where('event.id', $event->getKey())
                ->where('attendee.name', 'Ana Pérez')
                ->where('ticketUrl', route('public.ticket.show', ['token' => $token])));
    }

    public function test_a_paid_registration_gets_a_ticket_only_after_verification(): void
    {
        Storage::fake('local');

        [$church, $event, $admin, $receipt, $registration, $token] = $this->paidRegistration();

        $this->get(route('public.ticket.show', ['token' => $token]))->assertNotFound();

        $this->actingAs($admin)
            ->patch(route('events.receipts.verify', [
                'church' => $church,
                'event' => $event->getKey(),
                'receipt' => $receipt->getKey(),
            ]))
            ->assertRedirect();

        $registration->refresh();
        $this->assertNotNull($registration->ticket_code_hash);
        $this->assertNotNull($registration->issued_at);

        $this->get(route('public.ticket.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/ticket')
                ->where('attendee.paymentStatus', PaymentStatus::Paid->value));
    }

    public function test_staff_can_check_in_a_valid_ticket_and_it_is_idempotent(): void
    {
        $church = $this->createChurch('tickets-check-in');
        $admin = $this->createMember($church, 'admin');
        $event = $this->createEvent($church);

        $token = $this->freeRegistrationToken($church, $event);
        $ticketUrl = route('public.ticket.show', ['token' => $token]);

        $this->actingAs($admin)
            ->post(route('events.check-in.store', [
                'church' => $church,
                'event' => $event->getKey(),
            ]), ['code' => $ticketUrl])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('attendance_records', [
            'church_id' => $church->getKey(),
            'event_id' => $event->getKey(),
            'status' => 'present',
            'source' => 'device',
        ]);

        $this->actingAs($admin)
            ->post(route('events.check-in.store', [
                'church' => $church,
                'event' => $event->getKey(),
            ]), ['code' => $token])
            ->assertRedirect();

        $this->assertSame(1, AttendanceRecord::withoutGlobalScope('church')->count());
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'attendance.recorded',
        ]);
    }

    public function test_check_in_rejects_invalid_or_unpaid_tickets(): void
    {
        Storage::fake('local');

        $church = $this->createChurch('tickets-invalidos');
        $admin = $this->createMember($church, 'admin');
        $event = $this->createEvent($church);

        $this->actingAs($admin)
            ->post(route('events.check-in.store', [
                'church' => $church,
                'event' => $event->getKey(),
            ]), ['code' => 'no-existe'])
            ->assertSessionHas('error');

        [$paidChurch, $paidEvent, $paidAdmin, , , $token] = $this->paidRegistration();

        $this->actingAs($paidAdmin)
            ->post(route('events.check-in.store', [
                'church' => $paidChurch,
                'event' => $paidEvent->getKey(),
            ]), ['code' => $token])
            ->assertSessionHas('error');

        $this->assertSame(0, AttendanceRecord::withoutGlobalScope('church')->count());
    }

    public function test_check_in_page_renders_for_staff(): void
    {
        $church = $this->createChurch('tickets-ui');
        $admin = $this->createMember($church, 'admin');
        $event = $this->createEvent($church);

        $this->actingAs($admin)
            ->get(route('events.check-in.index', [
                'church' => $church,
                'event' => $event->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/check-in')
                ->where('event.id', $event->getKey()));
    }

    private function freeRegistrationToken(Church $church, Event $event): string
    {
        $response = $this->post(route('public.event.register', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]), [
            'first_name' => 'Beto',
            'last_name' => 'Rojas',
            'email' => 'beto-'.uniqid().'@example.com',
            'guest_count' => 0,
            'website' => '',
        ]);

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);

        return basename(parse_url($location, PHP_URL_PATH) ?? '');
    }

    /**
     * @return array{0: Church, 1: Event, 2: User, 3: PaymentReceipt, 4: EventRegistration, 5: string}
     */
    private function paidRegistration(): array
    {
        $church = $this->createChurch('tickets-pago-'.uniqid());
        $this->createCurrency($church, 'USD');
        $admin = $this->createMember($church, 'admin');
        $event = $this->createEvent($church, [
            'is_paid' => true,
            'price' => '20.00',
            'currency_code' => 'USD',
        ]);

        $response = $this->post(route('public.event.register', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]), [
            'first_name' => 'Carla',
            'last_name' => 'Díaz',
            'email' => 'carla-'.uniqid().'@example.com',
            'guest_count' => 0,
            'website' => '',
        ]);

        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $token = basename(parse_url($location, PHP_URL_PATH) ?? '');

        $this->post(route('public.receipt.store', ['token' => $token]), [
            'amount' => '20.00',
            'file' => UploadedFile::fake()->image('comprobante.png'),
            'website' => '',
        ])->assertSessionHasNoErrors();

        $registration = EventRegistration::withoutGlobalScope('church')
            ->latest('id')
            ->firstOrFail();
        $receipt = PaymentReceipt::withoutGlobalScope('church')
            ->latest('id')
            ->firstOrFail();

        return [$church, $event, $admin, $receipt, $registration, $token];
    }

    private function createEvent(Church $church, array $attributes = []): Event
    {
        return app(CurrentChurch::class)->run($church, fn (): Event => Event::create([
            'title' => $attributes['title'] ?? 'Evento con entrada',
            'slug' => $attributes['slug'] ?? 'evento-entrada-'.uniqid(),
            'description' => 'Evento de prueba.',
            'location' => 'Templo',
            'starts_at' => now()->addDays(5),
            'timezone' => 'America/Caracas',
            'status' => EventStatus::Published->value,
            'is_public' => true,
            'is_paid' => $attributes['is_paid'] ?? false,
            'price' => $attributes['price'] ?? null,
            'currency_code' => $attributes['currency_code'] ?? null,
        ]));
    }

    private function createCurrency(Church $church, string $code): void
    {
        app(CurrentChurch::class)->run($church, fn (): ChurchCurrency => ChurchCurrency::create([
            'currency_code' => $code,
            'is_default' => true,
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
