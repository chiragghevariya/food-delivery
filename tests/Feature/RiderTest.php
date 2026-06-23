<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tests for the rider endpoints (Phase 3): role middleware, accepting jobs,
 * the pickup/deliver flow, status-transition rules and history.
 */
class RiderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // make sure spatie's cached roles don't leak between tests
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Create a user with the given role (role is created on the "api" guard). */
    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->assignRole(Role::findOrCreate($role, 'api'));

        return $user;
    }

    /** Create an order and move it all the way to "ready_for_pickup". */
    private function makeReadyOrder(float $lat = 24.8600, float $lng = 67.0000): Order
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $owner    = User::factory()->create(['role' => 'restaurant_owner']);

        $restaurant = Restaurant::create([
            'user_id' => $owner->id,
            'name'    => 'Pickup Spot',
            'address' => '1 Food St',
            'lat'     => $lat,
            'lng'     => $lng,
            'is_open' => true,
        ]);
        $item = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Burger',
            'price'         => 10.00,
            'is_available'  => true,
        ]);

        $order = app(OrderService::class)->placeOrder($customer, [
            'restaurant_id' => $restaurant->id,
            'items'         => [['menu_item_id' => $item->id, 'quantity' => 1]],
            'address'       => 'Deliver here',
        ]);

        // pending -> accepted -> preparing -> ready_for_pickup
        $order->transitionTo(OrderStatus::ACCEPTED);
        $order->transitionTo(OrderStatus::PREPARING);
        $order->transitionTo(OrderStatus::READY_FOR_PICKUP);

        return $order->fresh();
    }

    public function test_non_riders_are_blocked_by_role_middleware(): void
    {
        $customer = $this->makeUserWithRole('customer');

        $this->actingAs($customer, 'api')
            ->getJson('/api/rider/orders/available')
            ->assertStatus(403);
    }

    public function test_guests_cannot_access_rider_routes(): void
    {
        $this->getJson('/api/rider/orders/available')->assertStatus(401);
    }

    public function test_rider_sees_available_orders_only(): void
    {
        $rider = $this->makeUserWithRole('rider');

        $this->makeReadyOrder(); // available

        // an order already taken by another rider must NOT appear
        $taken = $this->makeReadyOrder();
        $taken->update(['rider_id' => User::factory()->create()->id]);

        $this->actingAs($rider, 'api')
            ->getJson('/api/rider/orders/available')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_available_can_filter_by_distance(): void
    {
        $rider = $this->makeUserWithRole('rider');

        $this->makeReadyOrder(24.8605, 67.0005); // a few hundred metres away
        $this->makeReadyOrder(25.5000, 68.0000); // far away

        $response = $this->actingAs($rider, 'api')
            ->getJson('/api/rider/orders/available?lat=24.86&lng=67.00&radius=5')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertArrayHasKey('distance_km', $response->json('data.0'));
    }

    public function test_rider_can_accept_an_order(): void
    {
        $rider = $this->makeUserWithRole('rider');
        $order = $this->makeReadyOrder();

        $this->actingAs($rider, 'api')
            ->postJson("/api/rider/orders/{$order->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'ready_for_pickup'); // status unchanged, rider now assigned

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'rider_id' => $rider->id]);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'note'     => "Rider {$rider->name} accepted the delivery.",
        ]);
    }

    public function test_two_riders_cannot_accept_the_same_order(): void
    {
        $rider1 = $this->makeUserWithRole('rider');
        $rider2 = $this->makeUserWithRole('rider');
        $order  = $this->makeReadyOrder();

        $this->actingAs($rider1, 'api')->postJson("/api/rider/orders/{$order->id}/accept")->assertOk();
        $this->actingAs($rider2, 'api')->postJson("/api/rider/orders/{$order->id}/accept")->assertStatus(422);
    }

    public function test_rider_cannot_accept_an_order_that_is_not_ready(): void
    {
        $rider = $this->makeUserWithRole('rider');

        // a brand new (pending) order
        $customer   = User::factory()->create();
        $owner      = User::factory()->create();
        $restaurant = Restaurant::create(['user_id' => $owner->id, 'name' => 'R', 'address' => 'a', 'is_open' => true]);
        $item       = MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'I', 'price' => 5, 'is_available' => true]);
        $pending    = app(OrderService::class)->placeOrder($customer, [
            'restaurant_id' => $restaurant->id,
            'items'         => [['menu_item_id' => $item->id, 'quantity' => 1]],
            'address'       => 'a',
        ]);

        $this->actingAs($rider, 'api')
            ->postJson("/api/rider/orders/{$pending->id}/accept")
            ->assertStatus(422);
    }

    public function test_full_pickup_and_deliver_flow(): void
    {
        $rider = $this->makeUserWithRole('rider');
        $order = $this->makeReadyOrder();

        $this->actingAs($rider, 'api')->postJson("/api/rider/orders/{$order->id}/accept")->assertOk();

        $this->actingAs($rider, 'api')
            ->postJson("/api/rider/orders/{$order->id}/picked-up")
            ->assertOk()
            ->assertJsonPath('data.status', 'picked_up');

        $this->actingAs($rider, 'api')
            ->postJson("/api/rider/orders/{$order->id}/delivered")
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'delivered']);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'status' => 'picked_up']);
        $this->assertDatabaseHas('order_status_history', ['order_id' => $order->id, 'status' => 'delivered']);
    }

    public function test_rider_cannot_act_on_another_riders_order(): void
    {
        $rider1 = $this->makeUserWithRole('rider');
        $rider2 = $this->makeUserWithRole('rider');
        $order  = $this->makeReadyOrder();

        $this->actingAs($rider1, 'api')->postJson("/api/rider/orders/{$order->id}/accept")->assertOk();

        // rider2 is not assigned to this order
        $this->actingAs($rider2, 'api')
            ->postJson("/api/rider/orders/{$order->id}/picked-up")
            ->assertStatus(403);
    }

    public function test_status_steps_cannot_be_skipped(): void
    {
        $rider = $this->makeUserWithRole('rider');
        $order = $this->makeReadyOrder();

        $this->actingAs($rider, 'api')->postJson("/api/rider/orders/{$order->id}/accept")->assertOk();

        // trying to deliver before picking up should be rejected
        $this->actingAs($rider, 'api')
            ->postJson("/api/rider/orders/{$order->id}/delivered")
            ->assertStatus(422);
    }

    public function test_history_lists_delivered_orders(): void
    {
        $rider = $this->makeUserWithRole('rider');
        $order = $this->makeReadyOrder();

        $this->actingAs($rider, 'api')->postJson("/api/rider/orders/{$order->id}/accept");
        $this->actingAs($rider, 'api')->postJson("/api/rider/orders/{$order->id}/picked-up");
        $this->actingAs($rider, 'api')->postJson("/api/rider/orders/{$order->id}/delivered");

        $this->actingAs($rider, 'api')
            ->getJson('/api/rider/orders/history')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $order->id);
    }
}
