<?php

namespace Tests\Feature\Admin;

use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Support\Plans\PlanEnforcement;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminChurchBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_platform_admin_can_issue_a_usd_invoice(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-facturable');

        $this->actingAs($admin, 'platform')
            ->post(route('admin.churches.invoices.store', $church), [
                'amount' => '150.50',
                'description' => 'Suscripción mensual',
                'notes' => 'Acuerdo de piloto.',
                'due_at' => now()->addDays(10)->toDateString(),
            ])
            ->assertRedirect(route('admin.churches.invoices', $church))
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->firstOrFail();

        $this->assertSame(15050, $invoice->amount_minor);
        $this->assertSame('USD', $invoice->currency_code);
        $this->assertSame('issued', $invoice->status);
        $this->assertSame($church->getKey(), $invoice->church_id);
        $this->assertSame('Iglesia facturable', $invoice->church_name);
        $this->assertStringStartsWith('INV-', $invoice->number);
        $this->assertSame($admin->getKey(), $invoice->issued_by_platform_admin_id);

        $this->assertDatabaseHas('platform_audit_entries', [
            'church_id' => $church->getKey(),
            'platform_admin_id' => $admin->getKey(),
            'action' => 'invoice.issued',
        ]);
    }

    public function test_invoice_amount_validation_rejects_invalid_values(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-monto-invalido');

        foreach (['0', '-5', '12.345', 'abc'] as $amount) {
            $this->actingAs($admin, 'platform')
                ->post(route('admin.churches.invoices.store', $church), [
                    'amount' => $amount,
                ])
                ->assertSessionHasErrors('amount');
        }

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_payments_can_be_registered_incrementally_until_the_invoice_is_paid(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-pagos');
        $invoice = $this->createInvoice($church, 15000);

        $this->actingAs($admin, 'platform')
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => '50.00',
                'method' => 'transfer',
                'reference' => 'TRX-001',
                'notes' => 'Primer abono.',
                'paid_at' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('issued', $invoice->status);
        $this->assertNull($invoice->paid_at);
        $this->assertSame(5000, $invoice->paidMinor());
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->getKey(),
            'amount_minor' => 5000,
            'method' => 'transfer',
            'reference' => 'TRX-001',
            'notes' => 'Primer abono.',
            'recorded_by_platform_admin_id' => $admin->getKey(),
        ]);

        $this->actingAs($admin, 'platform')
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => '100.00',
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
        $this->assertSame(15000, $invoice->paidMinor());
        $this->assertSame(0, $invoice->remainingMinor());
        $this->assertSame(2, $invoice->payments()->count());
        $this->assertSame(
            2,
            $church->auditEntries()->where('action', 'payment.recorded')->count(),
        );
    }

    public function test_a_payment_cannot_exceed_the_outstanding_balance_or_target_a_void_invoice(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-pago-excedido');
        $invoice = $this->createInvoice($church, 5000);

        $this->actingAs($admin, 'platform')
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => '60.00',
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('amount');

        $this->actingAs($admin, 'platform')
            ->put(route('admin.invoices.status', $invoice), [
                'status' => 'void',
                'reason' => 'Error de emisión.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin, 'platform')
            ->post(route('admin.invoices.payments.store', $invoice->refresh()), [
                'amount' => '10.00',
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Payment::query()->count());
        $this->assertSame('void', $invoice->refresh()->status);
    }

    public function test_invoice_states_follow_the_allowed_transitions(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-estados-factura');
        $invoice = $this->createInvoice($church, 10000, now()->subDay());

        // Reading the billing screen persists the automatic issued -> overdue state.
        $this->actingAs($admin, 'platform')
            ->get(route('admin.churches.invoices', $church))
            ->assertOk();

        $this->assertSame('overdue', $invoice->refresh()->status);

        $this->actingAs($admin, 'platform')
            ->put(route('admin.invoices.status', $invoice), [
                'status' => 'paid',
            ])
            ->assertSessionHasErrors('status');

        $this->actingAs($admin, 'platform')
            ->put(route('admin.invoices.status', $invoice), [
                'status' => 'void',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($admin, 'platform')
            ->put(route('admin.invoices.status', $invoice), [
                'status' => 'void',
                'reason' => 'Duplicada.',
            ])
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('void', $invoice->status);
        $this->assertNotNull($invoice->voided_at);
        $this->assertSame('Duplicada.', $invoice->void_reason);
        $this->assertDatabaseHas('platform_audit_entries', [
            'church_id' => $church->getKey(),
            'action' => 'invoice.status_changed',
        ]);
    }

    public function test_a_paid_invoice_cannot_be_voided(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-pagada');
        $invoice = $this->createInvoice($church, 3000);
        $this->createPayment($invoice, 3000);
        $invoice->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        $this->actingAs($admin, 'platform')
            ->put(route('admin.invoices.status', $invoice), [
                'status' => 'void',
                'reason' => 'No permitido.',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('paid', $invoice->refresh()->status);
    }

    public function test_archived_churches_cannot_be_invoiced_or_receive_payments(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-archivada-factura', 'archived');
        $invoice = $this->createInvoice($church, 4000);

        $this->actingAs($admin, 'platform')
            ->post(route('admin.churches.invoices.store', $church), [
                'amount' => '10.00',
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'platform')
            ->post(route('admin.invoices.payments.store', $invoice), [
                'amount' => '10.00',
                'method' => 'cash',
                'paid_at' => now()->toDateString(),
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'platform')
            ->put(route('admin.invoices.status', $invoice), [
                'status' => 'void',
                'reason' => 'Cierre.',
            ])
            ->assertForbidden();

        $this->assertSame('issued', $invoice->refresh()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_church_users_cannot_access_platform_billing_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/invoices')
            ->assertRedirect(route('admin.login'));
    }

    public function test_the_global_billing_screen_lists_and_filters_invoices(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $alpha = $this->createChurch('iglesia-listado-alpha');
        $beta = $this->createChurch('iglesia-listado-beta');

        $issued = $this->createInvoice($alpha, 1000);
        $paid = $this->createInvoice($beta, 2000);
        $paid->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        $this->actingAs($admin, 'platform')
            ->get('/admin/invoices')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/invoices/index')
                ->has('invoices', 2)
                ->where('pagination.total', 2));

        $this->actingAs($admin, 'platform')
            ->get('/admin/invoices?status=paid')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices', 1)
                ->where('invoices.0.number', $paid->number)
                ->where('invoices.0.church.slug', 'iglesia-listado-beta'));

        $this->actingAs($admin, 'platform')
            ->get('/admin/invoices?search='.$issued->number)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('invoices', 1)
                ->where('invoices.0.id', $issued->getKey()));
    }

    public function test_church_admin_and_finance_see_only_their_invoice_history(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $alpha = $this->createChurch('iglesia-alpha-facturas');
        $beta = $this->createChurch('iglesia-beta-facturas');

        $alphaInvoice = $this->createInvoice($alpha, 12000);
        $betaInvoice = $this->createInvoice($beta, 9000);

        $alphaAdmin = $this->createMembership($alpha, 'admin');
        $alphaFinance = $this->createMembership($alpha, 'finance');
        $alphaLeader = $this->createMembership($alpha, 'leader');

        $this->actingAs($alphaAdmin)
            ->get(route('churches.show', $alpha))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('churches/show')
                ->where('billing.canView', true)
                ->has('billing.invoices', 1)
                ->where('billing.invoices.0.id', $alphaInvoice->getKey())
                ->where('billing.invoices.0.number', $alphaInvoice->number));

        $this->actingAs($alphaFinance)
            ->get(route('churches.show', $alpha))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('billing.invoices', 1)
                ->where('billing.invoices.0.id', $alphaInvoice->getKey()));

        $this->actingAs($alphaLeader)
            ->get(route('churches.show', $alpha))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('billing', null));

        $this->assertNotSame($alphaInvoice->getKey(), $betaInvoice->getKey());
    }

    private function createChurch(string $slug, string $status = 'active'): Church
    {
        $church = Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => $status,
            'country_code' => 'VE',
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
            'locale' => 'es',
            'archived_at' => $status === 'archived' ? now() : null,
        ]);

        $this->withChurchContext($church, function (): void {
            PlanEnforcement::without(function (): void {
                ChurchCurrency::create([
                    'currency_code' => 'USD',
                    'is_default' => true,
                ]);
            });
        });

        return $church;
    }

    private function createMembership(Church $church, string $role): User
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

    private function createInvoice(Church $church, int $amountMinor, mixed $dueAt = null): Invoice
    {
        return Invoice::create([
            'church_id' => $church->getKey(),
            'church_name' => $church->name,
            'church_slug' => $church->slug,
            'number' => 'INV-TEST-'.uniqid(),
            'status' => 'issued',
            'currency_code' => 'USD',
            'amount_minor' => $amountMinor,
            'description' => 'Suscripción',
            'issued_at' => now(),
            'due_at' => $dueAt ?? now()->addDays(10),
        ]);
    }

    private function createPayment(Invoice $invoice, int $amountMinor): Payment
    {
        return Payment::create([
            'invoice_id' => $invoice->getKey(),
            'church_id' => $invoice->church_id,
            'amount_minor' => $amountMinor,
            'currency_code' => 'USD',
            'method' => 'transfer',
            'paid_at' => now(),
        ]);
    }

    private function withChurchContext(Church $church, callable $callback): mixed
    {
        $currentChurch = app(CurrentChurch::class);
        $currentChurch->set($church);

        try {
            return $callback();
        } finally {
            $currentChurch->clear();
        }
    }
}
