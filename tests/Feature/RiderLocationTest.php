<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RiderLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole(Role::findOrCreate($role, 'api'));
        return $user;
    }

    public function test_rider_can_update_their_location(): void
    {
        $rider = $this->makeUserWithRole('rider');

        $this->actingAs($rider, 'api')
            ->postJson('/api/rider/location', ['lat' => 24.8607, 'lng' => 67.0011])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id'  => $rider->id,
            'lat' => 24.8607,
            'lng' => 67.0011,
        ]);
    }

    public function test_non_riders_cannot_update_location(): void
    {
        $customer = $this->makeUserWithRole('customer');

        $this->actingAs($customer, 'api')
            ->postJson('/api/rider/location', ['lat' => 1, 'lng' => 2])
            ->assertStatus(403);
    }

    public function test_location_is_validated(): void
    {
        $rider = $this->makeUserWithRole('rider');

        $this->actingAs($rider, 'api')
            ->postJson('/api/rider/location', ['lat' => 999, 'lng' => 'abc'])
            ->assertStatus(422);
    }
}
