<?php

namespace Tests\Feature\Admin;

use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Person;
use App\Models\PlatformAuditEntry;
use App\Models\User;
use App\Support\Plans\PlanEnforcement;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PurgeArchivedChurchesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_command_requires_an_explicit_valid_date(): void
    {
        $church = $this->createChurch('iglesia-sin-fecha', 'archived', now()->subDays(10));

        $this->assertSame(1, Artisan::call('churches:purge', ['--dry-run' => true]));
        $this->assertStringContainsString('--date', Artisan::output());

        $this->assertSame(1, Artisan::call('churches:purge', ['--date' => '2026-02-31']));
        $this->assertStringContainsString('no es válida', Artisan::output());

        $this->assertDatabaseHas('churches', ['id' => $church->getKey()]);
    }

    public function test_a_dry_run_lists_candidates_without_deleting_anything(): void
    {
        $church = $this->createChurch('iglesia-dry-run', 'archived', now()->subDays(10));

        $this->artisan('churches:purge', [
            '--date' => now()->toDateString(),
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Dry run')
            ->expectsOutputToContain('iglesia-dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseHas('churches', ['id' => $church->getKey()]);
        $this->assertDatabaseCount('platform_audit_entries', 0);
    }

    public function test_only_archived_churches_at_or_before_the_cutoff_are_purged(): void
    {
        $old = $this->createChurch('iglesia-vieja', 'archived', now()->subDays(40));
        $recent = $this->createChurch('iglesia-reciente', 'archived', now()->subDays(5));
        $active = $this->createChurch('iglesia-activa', 'active', null);

        $this->assertSame(0, Artisan::call('churches:purge', [
            '--date' => now()->subDays(20)->toDateString(),
        ]));

        $this->assertDatabaseMissing('churches', ['id' => $old->getKey()]);
        $this->assertDatabaseHas('churches', ['id' => $recent->getKey()]);
        $this->assertDatabaseHas('churches', ['id' => $active->getKey()]);

        $this->assertDatabaseHas('platform_audit_entries', [
            'action' => 'church.purged',
            'church_id' => $old->getKey(),
            'platform_admin_id' => null,
            'subject_id' => $old->getKey(),
        ]);
    }

    public function test_purging_removes_tenant_data_but_preserves_audit_and_billing_history(): void
    {
        $church = $this->createChurch('iglesia-purga-historial', 'archived', now()->subDays(30));
        $user = User::factory()->create();

        ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->withChurchContext($church, function (): void {
            Person::create(['first_name' => 'Ana', 'status' => 'active']);
        });

        $invoice = Invoice::create([
            'church_id' => $church->getKey(),
            'church_name' => $church->name,
            'church_slug' => $church->slug,
            'number' => 'INV-PURGE-001',
            'status' => 'paid',
            'currency_code' => 'USD',
            'amount_minor' => 5000,
            'issued_at' => now()->subDays(20),
            'paid_at' => now()->subDays(15),
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->getKey(),
            'church_id' => $church->getKey(),
            'amount_minor' => 5000,
            'currency_code' => 'USD',
            'method' => 'transfer',
            'reference' => 'TRX-PURGE',
            'paid_at' => now()->subDays(15),
        ]);

        $this->assertSame(0, Artisan::call('churches:purge', [
            '--date' => now()->toDateString(),
        ]));

        $this->assertDatabaseMissing('churches', ['id' => $church->getKey()]);
        $this->assertDatabaseMissing('church_memberships', ['church_id' => $church->getKey()]);
        $this->assertDatabaseMissing('people', ['church_id' => $church->getKey()]);

        $invoice->refresh();
        $this->assertNull($invoice->church_id);
        $this->assertSame('iglesia-purga-historial', $invoice->church_slug);
        $this->assertSame('Iglesia purga historial', $invoice->church_name);

        $payment->refresh();
        $this->assertNull($payment->church_id);
        $this->assertSame($invoice->getKey(), $payment->invoice_id);
        $this->assertSame('TRX-PURGE', $payment->reference);

        $this->assertDatabaseHas('platform_audit_entries', [
            'action' => 'church.purged',
            'church_id' => $church->getKey(),
            'platform_admin_id' => null,
        ]);

        $audit = PlatformAuditEntry::query()
            ->where('action', 'church.purged')
            ->where('church_id', $church->getKey())
            ->firstOrFail();

        $this->assertSame(1, $audit->metadata['counts']['invoices']);
        $this->assertSame(1, $audit->metadata['counts']['payments']);
        $this->assertSame('iglesia-purga-historial', $audit->metadata['church']['slug']);
    }

    private function createChurch(string $slug, string $status, ?Carbon $archivedAt): Church
    {
        $church = Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => $status,
            'country_code' => 'VE',
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
            'locale' => 'es',
            'archived_at' => $archivedAt,
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
