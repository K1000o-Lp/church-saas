<?php

namespace Tests\Feature\Admin;

use App\Models\Church;
use App\Models\ChurchCurrency;
use App\Models\ChurchMembership;
use App\Models\Contribution;
use App\Models\Event;
use App\Models\Person;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Support\Plans\PlanEnforcement;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminChurchManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_platform_backoffice_lists_churches_with_aggregate_metrics(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $alpha = $this->createChurch('alpha-iglesia', 'Alpha Iglesia');
        $beta = $this->createChurch('beta-iglesia', 'Beta Iglesia', 'suspended');

        $this->createMembership($alpha, 'active');
        $this->createPerson($alpha, 'active');
        $this->createPerson($alpha, 'archived');
        $this->createEvent($alpha);

        $this->actingAs($admin, 'platform')
            ->get('/admin/churches')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/churches/index')
                ->has('churches', 2)
                ->where('churches.0.name', 'Alpha Iglesia')
                ->where('churches.0.metrics.people', 1)
                ->where('churches.0.metrics.activeMemberships', 1)
                ->where('churches.0.metrics.events', 1)
                ->where('churches.1.name', 'Beta Iglesia')
                ->where('churches.1.status', 'suspended')
                ->where('pagination.total', 2));
    }

    public function test_archived_churches_are_hidden_by_default_and_can_be_filtered(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $this->createChurch('iglesia-archivada', 'Iglesia Archivada', 'archived');

        $this->actingAs($admin, 'platform')
            ->get('/admin/churches')
            ->assertInertia(fn (Assert $page) => $page->has('churches', 0));

        $this->actingAs($admin, 'platform')
            ->get('/admin/churches?status=archived')
            ->assertInertia(fn (Assert $page) => $page
                ->has('churches', 1)
                ->where('churches.0.status', 'archived'));
    }

    public function test_the_platform_admin_can_view_church_detail_without_tenant_data(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-detalle', 'Iglesia Detalle');
        $this->createMembership($church, 'active');
        $this->createPerson($church, 'active');

        $this->actingAs($admin, 'platform')
            ->get(route('admin.churches.show', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/churches/show')
                ->where('church.slug', 'iglesia-detalle')
                ->where('church.metrics.people', 1)
                ->where('church.metrics.activeMemberships', 1)
                ->where('canEdit', true)
                ->has('currencies', 1)
                ->has('auditEntries', 0));
    }

    public function test_the_platform_admin_can_update_church_metadata_and_currencies(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-editable', 'Iglesia Editable', currencies: ['USD', 'VES']);

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.update', $church), [
                'name' => 'Iglesia Renombrada',
                'country_code' => 'co',
                'timezone' => 'America/Bogota',
                'locale' => 'es_CO',
                'currency_code' => 'cop',
                'additional_currencies' => ['usd'],
            ])
            ->assertRedirect(route('admin.churches.show', $church))
            ->assertSessionHasNoErrors();

        $church->refresh();

        $this->assertSame('Iglesia Renombrada', $church->name);
        $this->assertSame('CO', $church->country_code);
        $this->assertSame('America/Bogota', $church->timezone);
        $this->assertSame('es_CO', $church->locale);
        $this->assertSame('COP', $church->currency_code);

        $this->assertDatabaseHas('church_currencies', [
            'church_id' => $church->getKey(),
            'currency_code' => 'COP',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('church_currencies', [
            'church_id' => $church->getKey(),
            'currency_code' => 'USD',
            'is_default' => false,
        ]);
        $this->assertDatabaseMissing('church_currencies', [
            'church_id' => $church->getKey(),
            'currency_code' => 'VES',
        ]);

        $this->assertDatabaseHas('platform_audit_entries', [
            'platform_admin_id' => $admin->getKey(),
            'church_id' => $church->getKey(),
            'action' => 'church.updated',
        ]);
    }

    public function test_a_currency_with_contributions_cannot_be_removed(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-con-aportes', 'Iglesia con Aportes', currencies: ['USD', 'VES']);

        $this->withChurchContext($church, function (): void {
            Contribution::create([
                'amount' => '10.00',
                'currency_code' => 'VES',
                'method' => 'cash',
                'contributed_at' => now(),
            ]);
        });

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.update', $church), [
                'name' => 'Nombre que no debe persistir',
                'country_code' => 'VE',
                'timezone' => 'America/Caracas',
                'locale' => 'es',
                'currency_code' => 'USD',
                'additional_currencies' => [],
            ])
            ->assertSessionHasErrors('additional_currencies');

        $this->assertSame('Iglesia con Aportes', $church->refresh()->name);
        $this->assertDatabaseHas('church_currencies', [
            'church_id' => $church->getKey(),
            'currency_code' => 'VES',
        ]);
    }

    public function test_an_archived_church_cannot_be_edited(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-archivada-edicion', 'Iglesia Archivada', 'archived');

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.update', $church), [
                'name' => 'Nombre Nuevo',
                'country_code' => 'VE',
                'timezone' => 'America/Caracas',
                'locale' => 'es',
                'currency_code' => 'USD',
                'additional_currencies' => [],
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame('Iglesia Archivada', $church->refresh()->name);
    }

    public function test_non_active_statuses_require_a_reason(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-motivo', 'Iglesia Motivo');

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.status', $church), [
                'status' => 'suspended',
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame('active', $church->refresh()->status);
    }

    public function test_the_platform_admin_can_suspend_archive_and_reactivate_a_church(): void
    {
        $admin = PlatformAdmin::factory()->create();
        $church = $this->createChurch('iglesia-estados', 'Iglesia Estados');

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.status', $church), [
                'status' => 'suspended',
                'reason' => 'Impago',
            ])
            ->assertRedirect(route('admin.churches.show', $church));

        $church->refresh();
        $this->assertSame('suspended', $church->status);
        $this->assertSame('Impago', $church->status_reason);
        $this->assertSame($admin->getKey(), $church->status_changed_by_platform_admin_id);
        $this->assertNotNull($church->status_changed_at);
        $this->assertNull($church->archived_at);

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.status', $church), [
                'status' => 'archived',
                'reason' => 'Cierre solicitado',
            ])
            ->assertRedirect(route('admin.churches.show', $church));

        $church->refresh();
        $this->assertSame('archived', $church->status);
        $this->assertNotNull($church->archived_at);

        $this->actingAs($admin, 'platform')
            ->put(route('admin.churches.status', $church), [
                'status' => 'active',
                'reason' => 'Cliente reactivado',
            ])
            ->assertRedirect(route('admin.churches.show', $church));

        $church->refresh();
        $this->assertSame('active', $church->status);
        $this->assertNull($church->archived_at);
        $this->assertSame(3, $church->auditEntries()->where('action', 'church.status_changed')->count());
    }

    /**
     * @param  array<int, string>  $currencies
     */
    private function createChurch(
        string $slug,
        string $name,
        string $status = 'active',
        array $currencies = ['USD'],
    ): Church {
        $church = Church::create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'country_code' => 'VE',
            'timezone' => 'America/Caracas',
            'currency_code' => $currencies[0],
            'locale' => 'es',
            'archived_at' => $status === 'archived' ? now() : null,
        ]);

        $this->withChurchContext($church, function () use ($currencies): void {
            // Fixtures may exceed the default plan to exercise legacy/over-limit data.
            PlanEnforcement::without(function () use ($currencies): void {
                foreach ($currencies as $index => $currency) {
                    ChurchCurrency::create([
                        'currency_code' => $currency,
                        'is_default' => $index === 0,
                    ]);
                }
            });
        });

        return $church;
    }

    private function createMembership(Church $church, string $status): ChurchMembership
    {
        return ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => User::factory()->create()->getKey(),
            'role' => 'admin',
            'status' => $status,
        ]);
    }

    private function createPerson(Church $church, string $status): Person
    {
        return $this->withChurchContext($church, fn (): Person => Person::create([
            'first_name' => 'Persona',
            'status' => $status,
        ]));
    }

    private function createEvent(Church $church): Event
    {
        return $this->withChurchContext($church, fn (): Event => Event::create([
            'title' => 'Culto',
            'status' => 'published',
        ]));
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
