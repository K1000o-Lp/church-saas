<?php

namespace Tests\Feature\Events;

use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\PaymentReceipt;
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

class PaymentReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_paid_registration_redirects_to_the_receipt_page(): void
    {
        $church = $this->createChurch('recibos-redirect');
        $this->createCurrency($church, 'USD');
        $event = $this->createPaidEvent($church);

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
        $this->assertStringContainsString('/comprobantes/', $location);

        $registration = EventRegistration::withoutGlobalScope('church')->firstOrFail();
        $this->assertSame(PaymentStatus::Pending->value, $registration->payment_status);
        $this->assertNotNull($registration->public_token_hash);

        $token = basename(parse_url($location, PHP_URL_PATH) ?? '');

        $this->get(route('public.receipt.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/receipt')
                ->where('event.id', $event->getKey())
                ->where('canUpload', true));
    }

    public function test_uploading_a_receipt_moves_the_registration_under_review(): void
    {
        Storage::fake('local');

        [$church, $event, $token, $registration] = $this->paidRegistrationWithToken();

        $this->post(route('public.receipt.store', ['token' => $token]), [
            'amount' => '15.00',
            'reference_number' => 'REF-001',
            'file' => UploadedFile::fake()->image('comprobante.png'),
            'website' => '',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $registration->refresh();
        $this->assertSame(PaymentStatus::UnderReview->value, $registration->payment_status);

        $receipt = PaymentReceipt::withoutGlobalScope('church')->firstOrFail();
        $this->assertSame('pending', $receipt->status);
        $this->assertSame('15.00', $receipt->amount);
        $this->assertSame('REF-001', $receipt->reference_number);
        Storage::disk('local')->assertExists($receipt->file_path);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'receipt.submitted',
        ]);
    }

    public function test_receipts_reject_invalid_files_and_duplicate_pending_uploads(): void
    {
        Storage::fake('local');

        [, , $token] = $this->paidRegistrationWithToken();

        $this->post(route('public.receipt.store', ['token' => $token]), [
            'amount' => '15.00',
            'file' => UploadedFile::fake()->create('nota.txt', 10, 'text/plain'),
            'website' => '',
        ])
            ->assertSessionHasErrors('file');

        $this->post(route('public.receipt.store', ['token' => $token]), [
            'amount' => '15.00',
            'file' => UploadedFile::fake()->image('comprobante.png'),
            'website' => '',
        ])
            ->assertSessionHasNoErrors();

        $this->post(route('public.receipt.store', ['token' => $token]), [
            'amount' => '15.00',
            'file' => UploadedFile::fake()->image('comprobante-2.png'),
            'website' => '',
        ])
            ->assertSessionHas('error');

        $this->assertSame(1, PaymentReceipt::withoutGlobalScope('church')->count());
    }

    public function test_an_admin_can_verify_a_receipt_and_confirm_the_registration(): void
    {
        Storage::fake('local');

        [$church, $event, $token, $registration] = $this->paidRegistrationWithToken();
        $admin = $this->createMember($church, 'admin');

        $this->post(route('public.receipt.store', ['token' => $token]), [
            'amount' => '15.00',
            'file' => UploadedFile::fake()->image('comprobante.png'),
            'website' => '',
        ]);

        $receipt = PaymentReceipt::withoutGlobalScope('church')->firstOrFail();

        $this->actingAs($admin)
            ->get(route('events.receipts.index', [
                'church' => $church,
                'event' => $event->getKey(),
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('events/receipts')
                ->has('receipts', 1)
                ->where('canManage', true));

        $this->actingAs($admin)
            ->patch(route('events.receipts.verify', [
                'church' => $church,
                'event' => $event->getKey(),
                'receipt' => $receipt->getKey(),
            ]))
            ->assertRedirect();

        $receipt->refresh();
        $registration->refresh();
        $this->assertSame('verified', $receipt->status);
        $this->assertSame($admin->getKey(), $receipt->verified_by);
        $this->assertSame(PaymentStatus::Paid->value, $registration->payment_status);
        $this->assertSame(RegistrationStatus::Confirmed->value, $registration->status);
        $this->assertDatabaseHas('church_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'receipt.verified',
        ]);
    }

    public function test_an_admin_can_reject_a_receipt_and_allow_a_new_upload(): void
    {
        Storage::fake('local');

        [$church, $event, $token, $registration] = $this->paidRegistrationWithToken();
        $admin = $this->createMember($church, 'admin');

        $this->post(route('public.receipt.store', ['token' => $token]), [
            'amount' => '15.00',
            'file' => UploadedFile::fake()->image('comprobante.png'),
            'website' => '',
        ]);

        $receipt = PaymentReceipt::withoutGlobalScope('church')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('events.receipts.reject', [
                'church' => $church,
                'event' => $event->getKey(),
                'receipt' => $receipt->getKey(),
            ]), ['reason' => 'Monto incorrecto.'])
            ->assertRedirect();

        $receipt->refresh();
        $registration->refresh();
        $this->assertSame('rejected', $receipt->status);
        $this->assertSame('Monto incorrecto.', $receipt->rejection_reason);
        $this->assertSame(PaymentStatus::Rejected->value, $registration->payment_status);

        $this->get(route('public.receipt.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('canUpload', true));
    }

    public function test_receipts_are_isolated_between_churches(): void
    {
        Storage::fake('local');

        [, $event, $token] = $this->paidRegistrationWithToken();

        $this->post(route('public.receipt.store', ['token' => $token]), [
            'amount' => '15.00',
            'file' => UploadedFile::fake()->image('comprobante.png'),
            'website' => '',
        ]);

        $receipt = PaymentReceipt::withoutGlobalScope('church')->firstOrFail();

        $beta = $this->createChurch('recibos-beta');
        $betaAdmin = $this->createMember($beta, 'admin');

        $this->actingAs($betaAdmin)
            ->get(route('events.receipts.index', [
                'church' => $beta,
                'event' => $event->getKey(),
            ]))
            ->assertNotFound();
    }

    public function test_an_invalid_receipt_token_returns_not_found(): void
    {
        $this->get(route('public.receipt.show', ['token' => 'invalid-token']))
            ->assertNotFound();
    }

    /**
     * @return array{0: Church, 1: Event, 2: string, 3: EventRegistration}
     */
    private function paidRegistrationWithToken(): array
    {
        $church = $this->createChurch('recibos-'.uniqid());
        $this->createCurrency($church, 'USD');
        $event = $this->createPaidEvent($church);

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
        $token = basename(parse_url($location, PHP_URL_PATH) ?? '');

        $registration = EventRegistration::withoutGlobalScope('church')
            ->latest('id')
            ->firstOrFail();

        return [$church, $event, $token, $registration];
    }

    private function createPaidEvent(Church $church): Event
    {
        return app(CurrentChurch::class)->run($church, fn (): Event => Event::create([
            'title' => 'Retiro con costo',
            'slug' => 'retiro-'.uniqid(),
            'description' => 'Evento de prueba.',
            'location' => 'Templo',
            'starts_at' => now()->addDays(10),
            'timezone' => 'America/Caracas',
            'status' => EventStatus::Published->value,
            'is_public' => true,
            'is_paid' => true,
            'price' => '15.00',
            'currency_code' => 'USD',
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
