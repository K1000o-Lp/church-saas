<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use App\Support\Tenancy\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChurchOnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_access_church_onboarding(): void
    {
        $this->get('/onboarding/church')->assertRedirect(route('login'));
        $this->post('/onboarding/church', [])->assertRedirect(route('login'));
    }

    public function test_the_onboarding_screen_exposes_the_available_options(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/onboarding/church')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('onboarding/church')
                ->has('countries', 2)
                ->has('currencies', 4)
                ->where('hasMemberships', false)
                ->where('initialPlan.name', 'Comunidad')
                ->where('initialPlan.currencyLimit', 1));
    }

    public function test_an_authenticated_user_creates_a_church_with_membership_and_currencies(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/onboarding/church', [
            'name' => 'Comunidad Esperanza',
            'slug' => 'Comunidad Esperanza',
            'country_code' => 've',
            'timezone' => 'America/Caracas',
            'currency_code' => 'usd',
            'additional_currencies' => [],
        ]);

        $church = Church::query()->where('slug', 'comunidad-esperanza')->firstOrFail();

        $response
            ->assertRedirect(route('churches.show', $church))
            ->assertSessionHas('current_church_id', $church->getKey())
            ->assertSessionHas('success');

        $this->assertSame('Comunidad Esperanza', $church->name);
        $this->assertSame('VE', $church->country_code);
        $this->assertSame('America/Caracas', $church->timezone);
        $this->assertSame('USD', $church->currency_code);
        $this->assertSame('active', $church->status);

        $this->assertDatabaseHas('church_memberships', [
            'church_id' => $church->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('church_currencies', [
            'church_id' => $church->getKey(),
            'currency_code' => 'USD',
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('church_subscriptions', [
            'church_id' => $church->getKey(),
            'status' => 'active',
        ]);

        $this->assertNull(app(CurrentChurch::class)->id());

        $this->actingAs($user)
            ->get(route('churches.show', $church))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('churches/show')
                ->where('membership.role', 'admin')
                ->has('currencies', 1)
                ->where('currencies.0.code', 'USD')
                ->where('currencies.0.isDefault', true));
    }

    public function test_additional_currencies_beyond_the_initial_plan_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/onboarding/church', $this->payload([
                'additional_currencies' => ['VES'],
            ]))
            ->assertSessionHasErrors('additional_currencies');

        $this->assertSame(0, Church::query()->count());
    }

    public function test_a_church_slug_must_be_unique(): void
    {
        $user = User::factory()->create();
        $this->createChurch('comunidad-esperanza');

        $this->actingAs($user)
            ->post('/onboarding/church', $this->payload([
                'slug' => 'Comunidad Esperanza',
            ]))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Church::query()->count());
    }

    public function test_the_primary_currency_cannot_be_repeated_as_an_additional_currency(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/onboarding/church', $this->payload([
                'currency_code' => 'USD',
                'additional_currencies' => ['USD', 'VES'],
            ]))
            ->assertSessionHasErrors('additional_currencies.0');

        $this->assertSame(0, Church::query()->count());
    }

    public function test_the_country_and_currency_must_be_supported(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/onboarding/church', $this->payload([
                'country_code' => 'AR',
                'currency_code' => 'ARS',
            ]))
            ->assertSessionHasErrors(['country_code', 'currency_code']);

        $this->assertSame(0, Church::query()->count());
    }

    public function test_a_user_with_memberships_can_create_another_church(): void
    {
        $user = User::factory()->create();
        $firstChurch = $this->createChurch('iglesia-original');
        $this->createMembership($firstChurch, $user);

        $this->actingAs($user)
            ->get('/onboarding/church')
            ->assertInertia(fn (Assert $page) => $page->where('hasMemberships', true));

        $this->actingAs($user)
            ->post('/onboarding/church', $this->payload([
                'name' => 'Iglesia Nueva',
                'slug' => 'iglesia-nueva',
            ]))
            ->assertSessionHasNoErrors();

        $secondChurch = Church::query()->where('slug', 'iglesia-nueva')->firstOrFail();

        $this->assertSame(2, $user->churchMemberships()->count());
        $this->assertDatabaseHas('church_memberships', [
            'church_id' => $secondChurch->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Iglesia de Prueba',
            'slug' => 'iglesia-de-prueba',
            'country_code' => 'VE',
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
            'additional_currencies' => [],
        ], $overrides);
    }

    private function createChurch(string $slug): Church
    {
        return Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'active',
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
        ]);
    }

    private function createMembership(Church $church, User $user): ChurchMembership
    {
        return ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
