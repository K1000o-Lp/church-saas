<?php

namespace Tests\Feature\Public;

use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Models\ChurchSubscription;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Person;
use App\Models\Plan;
use App\Models\User;
use App\Support\Events\EventStatus;
use App\Support\Events\PaymentStatus;
use App\Support\Events\RegistrationStatus;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_portal_lists_published_events(): void
    {
        $church = $this->createChurch('portal-publico');
        $event = $this->createEvent($church, ['title' => 'Culto de jóvenes']);

        $this->get(route('public.church', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/church')
                ->where('church.slug', $church->slug)
                ->has('events', 1)
                ->where('events.0.title', 'Culto de jóvenes'));

        $this->get(route('public.event', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/event')
                ->where('event.slug', $event->slug)
                ->where('event.registrationIsOpen', true));
    }

    public function test_inactive_churches_and_private_events_are_not_public(): void
    {
        $inactive = $this->createChurch('portal-inactiva', ['status' => 'inactive']);

        $this->get(route('public.church', $inactive))->assertNotFound();

        $church = $this->createChurch('portal-privada');
        $event = $this->createEvent($church, ['is_public' => false]);

        $this->get(route('public.event', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]))->assertNotFound();
    }

    public function test_a_public_registration_creates_a_visitor_and_a_confirmed_registration(): void
    {
        $church = $this->createChurch('portal-registro');
        $event = $this->createEvent($church);

        $this->post(route('public.event.register', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]), [
            'first_name' => 'Ana',
            'last_name' => 'Pérez',
            'email' => 'ana@example.com',
            'phone' => null,
            'guest_count' => 2,
            'website' => '',
        ])
            ->assertRedirectContains('/entradas/')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $person = Person::withoutGlobalScope('church')
            ->where('email', 'ana@example.com')
            ->firstOrFail();

        $this->assertSame('visitor', $person->status);

        $registration = EventRegistration::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame($person->getKey(), $registration->person_id);
        $this->assertSame(RegistrationStatus::Confirmed->value, $registration->status);
        $this->assertSame(PaymentStatus::NotRequired->value, $registration->payment_status);
        $this->assertSame(2, $registration->guest_count);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'registration.created',
        ]);
    }

    public function test_public_registration_reuses_an_existing_person_by_email(): void
    {
        $church = $this->createChurch('portal-dedupe');
        $event = $this->createEvent($church);
        $existing = $this->createPerson($church, [
            'first_name' => 'Beto',
            'last_name' => 'Rojas',
            'email' => 'beto@example.com',
            'status' => 'active',
        ]);

        $this->post(route('public.event.register', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]), [
            'first_name' => 'Beto',
            'last_name' => 'Rojas',
            'email' => 'BETO@example.com',
            'guest_count' => 0,
            'website' => '',
        ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            1,
            Person::withoutGlobalScope('church')->where('email', 'beto@example.com')->count(),
        );

        $registration = EventRegistration::withoutGlobalScope('church')->firstOrFail();
        $this->assertSame($existing->getKey(), $registration->person_id);
    }

    public function test_a_paid_public_registration_waits_for_payment(): void
    {
        $church = $this->createChurch('portal-pago');
        $this->createCurrency($church, 'USD');
        $event = $this->createEvent($church, [
            'is_paid' => true,
            'price' => '15.00',
            'currency_code' => 'USD',
        ]);

        $this->post(route('public.event.register', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]), [
            'first_name' => 'Carla',
            'last_name' => 'Díaz',
            'email' => 'carla@example.com',
            'guest_count' => 0,
            'website' => '',
        ])
            ->assertSessionHasNoErrors();

        $registration = EventRegistration::withoutGlobalScope('church')->firstOrFail();

        $this->assertSame(RegistrationStatus::Registered->value, $registration->status);
        $this->assertSame(PaymentStatus::Pending->value, $registration->payment_status);
    }

    public function test_public_registration_requires_contact_and_rejects_honeypots(): void
    {
        $church = $this->createChurch('portal-validacion');
        $event = $this->createEvent($church);

        $this->post(route('public.event.register', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]), [
            'first_name' => 'Diego',
            'guest_count' => 0,
            'website' => '',
        ])
            ->assertSessionHasErrors('email');

        $this->post(route('public.event.register', [
            'church' => $church,
            'eventSlug' => $event->slug,
        ]), [
            'first_name' => 'Diego',
            'email' => 'diego@example.com',
            'guest_count' => 0,
            'website' => 'spam',
        ])
            ->assertSessionHasErrors('website');

        $this->assertSame(0, EventRegistration::withoutGlobalScope('church')->count());
    }

    public function test_branding_requires_the_white_label_feature(): void
    {
        $church = $this->createChurch('portal-marca');
        $admin = $this->createMember($church, 'admin');

        $this->actingAs($admin)
            ->get(route('churches.branding.edit', $church))
            ->assertForbidden();
    }

    public function test_an_admin_can_update_branding_on_a_pro_plan(): void
    {
        Storage::fake('public');

        $church = $this->createChurch('portal-marca-pro');
        $admin = $this->createMember($church, 'admin');
        $pro = Plan::query()->where('key', 'ministerio_pro')->firstOrFail();

        ChurchSubscription::query()
            ->where('church_id', $church->getKey())
            ->update(['plan_id' => $pro->getKey()]);

        $this->actingAs($admin)
            ->get(route('churches.branding.edit', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('churches/settings/branding')
                ->where('church.slug', $church->slug));

        $this->actingAs($admin)
            ->post(route('churches.branding.update', $church), [
                'tagline' => 'Una iglesia para todos',
                'primary_color' => '#1f4d3a',
                'accent_color' => '#b8862f',
                'service_times' => 'Domingo 10:00 AM',
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
                'remove_logo' => false,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $church->refresh();
        $branding = $church->settings['branding'] ?? [];

        $this->assertSame('Una iglesia para todos', $branding['tagline']);
        $this->assertSame('#1f4d3a', $branding['primary_color']);
        $this->assertNotNull($branding['logo_path']);
        Storage::disk('public')->assertExists($branding['logo_path']);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'church.branding_updated',
        ]);
    }

    private function createEvent(Church $church, array $attributes = []): Event
    {
        return app(CurrentChurch::class)->run($church, fn (): Event => Event::create([
            'title' => $attributes['title'] ?? 'Evento público',
            'slug' => $attributes['slug'] ?? 'evento-publico-'.uniqid(),
            'description' => $attributes['description'] ?? 'Descripción del evento.',
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

    private function createPerson(Church $church, array $attributes): Person
    {
        return app(CurrentChurch::class)->run($church, fn (): Person => Person::create([
            'first_name' => $attributes['first_name'],
            'last_name' => $attributes['last_name'] ?? null,
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'status' => $attributes['status'] ?? 'active',
            'custom_fields' => (object) [],
        ]));
    }

    private function createCurrency(Church $church, string $code): void
    {
        app(CurrentChurch::class)->run($church, fn (): ChurchCurrency => ChurchCurrency::create([
            'currency_code' => $code,
            'is_default' => true,
        ]));
    }

    private function createChurch(string $slug, array $attributes = []): Church
    {
        return Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => $attributes['status'] ?? 'active',
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
