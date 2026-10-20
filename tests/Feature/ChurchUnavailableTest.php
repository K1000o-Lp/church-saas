<?php

namespace Tests\Feature;

use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChurchUnavailableTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_of_a_suspended_church_sees_the_unavailable_page(): void
    {
        $user = User::factory()->create();
        $church = $this->createChurch('iglesia-suspendida', 'suspended');
        $this->createMembership($church, $user);

        $this->actingAs($user)
            ->withSession(['current_church_id' => $church->getKey()])
            ->get(route('churches.show', $church))
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page
                ->component('churches/unavailable')
                ->where('church.name', 'Iglesia suspendida')
                ->where('status', 'suspended')
                ->where('statusLabel', 'Suspendida'))
            ->assertSessionMissing('current_church_id');
    }

    public function test_a_member_of_an_inactive_or_archived_church_sees_the_unavailable_page(): void
    {
        $user = User::factory()->create();
        $inactive = $this->createChurch('iglesia-inactiva', 'inactive');
        $archived = $this->createChurch('iglesia-archivada', 'archived');
        $this->createMembership($inactive, $user);
        $this->createMembership($archived, $user);

        $this->actingAs($user)
            ->get(route('churches.show', $inactive))
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page->where('status', 'inactive'));

        $this->actingAs($user)
            ->get(route('churches.show', $archived))
            ->assertStatus(403)
            ->assertInertia(fn (Assert $page) => $page->where('status', 'archived'));
    }

    public function test_a_non_member_cannot_learn_the_status_of_an_unavailable_church(): void
    {
        $user = User::factory()->create();
        $church = $this->createChurch('iglesia-privada-suspendida', 'suspended');

        $this->actingAs($user)
            ->get(route('churches.show', $church))
            ->assertNotFound();
    }

    public function test_a_suspended_membership_still_gets_a_not_found_response(): void
    {
        $user = User::factory()->create();
        $church = $this->createChurch('iglesia-membresia-suspendida', 'active');
        $this->createMembership($church, $user, 'suspended');

        $this->actingAs($user)
            ->get(route('churches.show', $church))
            ->assertNotFound();
    }

    private function createChurch(string $slug, string $status): Church
    {
        return Church::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => $status,
            'country_code' => 'VE',
            'timezone' => 'America/Caracas',
            'currency_code' => 'USD',
            'archived_at' => $status === 'archived' ? now() : null,
        ]);
    }

    private function createMembership(Church $church, User $user, string $status = 'active'): ChurchMembership
    {
        return ChurchMembership::create([
            'church_id' => $church->getKey(),
            'user_id' => $user->getKey(),
            'role' => 'admin',
            'status' => $status,
        ]);
    }
}
