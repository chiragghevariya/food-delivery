<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for placing, listing, viewing and cancelling orders (Phase 2).
 */
class OrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a customer, a restaurant and two menu items to order.
     *
     * @return array{0: User, 1: Restaurant, 2: array<string, MenuItem>}
     */
    private function makeWorld(): array
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $owner    = User::factory()->create(['role' => 'restaurant_owner']);

        $restaurant = Restaurant::create([
            'user_id' => $owner->id,
            'name'    => 'Test Pizza',
            'address' => 'Somewhere',
            'lat'     => 24.8600,
            'lng'     => 67.0000,
            'is_open' => true,
            'rating'  => 4.5,
        ]);

        $pizza = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Pizza',
            'price'         => 10.00,
            'is_available'  => true,
        ]);
        $coke = MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Coke',
            'price'         => 5.50,
            'is_available'  => true,
        ]);

        return [$customer, $restaurant, ['pizza' => $pizza, 'coke' => $coke]];
    }

    /** Place an order directly through the service (handy for test setup). */
    private function placeOrderFor(User $customer, Restaurant $restaurant, array $items): Order
    {
        return app(OrderService::class)->placeOrder($customer, [
            'restaurant_id' => $restaurant->id,
            'items'         => [['menu_item_id' => $items['pizza']->id, 'quantity' => 1]],
            'address'       => '123 Test Street',
        ]);
    }

    public function test_customer_can_place_an_order_with_correct_totals(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();

        $response = $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items'         => [
                ['menu_item_id' => $items['pizza']->id, 'quantity' => 2], // 10.00 x2 = 20.00
                ['menu_item_id' => $items['coke']->id,  'quantity' => 1], //  5.50 x1 =  5.50
            ],
            'address' => '123 Test Street',
            'lat'     => 24.86,
            'lng'     => 67.00,
            'notes'   => 'Ring the bell',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.subtotal', '25.50')
            ->assertJsonPath('data.delivery_fee', '2.00')
            ->assertJsonPath('data.total', '27.50')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonCount(1, 'data.status_history');

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'status'      => 'pending',
            'total'       => '27.50',
        ]);
        $this->assertDatabaseCount('order_items', 2);
        $this->assertDatabaseHas('order_status_history', ['status' => 'pending']);
    }

    public function test_prices_come_from_the_database_not_the_request(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();

        // The client tries to sneak in a fake price — it must be ignored.
        $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items'         => [
                ['menu_item_id' => $items['pizza']->id, 'quantity' => 1, 'unit_price' => 0.01],
            ],
            'address' => '123 Test Street',
        ])->assertCreated()
          ->assertJsonPath('data.subtotal', '10.00'); // real DB price, not 0.01
    }

    public function test_order_is_rejected_when_item_is_not_from_the_restaurant(): void
    {
        [$customer, $restaurant] = $this->makeWorld();

        // a menu item that belongs to a DIFFERENT restaurant
        $otherOwner = User::factory()->create();
        $other = Restaurant::create([
            'user_id' => $otherOwner->id, 'name' => 'Other', 'address' => 'x', 'is_open' => true,
        ]);
        $foreignItem = MenuItem::create([
            'restaurant_id' => $other->id, 'name' => 'Foreign', 'price' => 3.00, 'is_available' => true,
        ]);

        $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items'         => [['menu_item_id' => $foreignItem->id, 'quantity' => 1]],
            'address'       => '123 Test Street',
        ])->assertStatus(422);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_is_rejected_when_restaurant_is_closed(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();
        $restaurant->update(['is_open' => false]);

        $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items'         => [['menu_item_id' => $items['pizza']->id, 'quantity' => 1]],
            'address'       => '123 Test Street',
        ])->assertStatus(422);
    }

    public function test_customer_only_sees_their_own_orders(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();
        $this->placeOrderFor($customer, $restaurant, $items);

        $otherCustomer = User::factory()->create();
        $this->placeOrderFor($otherCustomer, $restaurant, $items);

        $this->actingAs($customer, 'api')->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_customer_can_view_own_order_but_not_others(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();
        $order = $this->placeOrderFor($customer, $restaurant, $items);

        // owner can see it (with items + history)
        $this->actingAs($customer, 'api')->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonCount(1, 'data.status_history');

        // a different user gets 403 Forbidden
        $intruder = User::factory()->create();
        $this->actingAs($intruder, 'api')->getJson("/api/orders/{$order->id}")
            ->assertStatus(403);
    }

    public function test_customer_can_cancel_a_pending_order(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();
        $order = $this->placeOrderFor($customer, $restaurant, $items);

        $this->actingAs($customer, 'api')->postJson("/api/orders/{$order->id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $order->id,
            'status'   => 'cancelled',
        ]);
    }

    public function test_customer_cannot_cancel_a_non_pending_order(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();
        $order = $this->placeOrderFor($customer, $restaurant, $items);

        // move it past "pending"
        $order->changeStatus(OrderStatus::ACCEPTED);

        $this->actingAs($customer, 'api')->postJson("/api/orders/{$order->id}/cancel")
            ->assertStatus(422);
    }

    public function test_placing_an_order_requires_authentication(): void
    {
        [, $restaurant, $items] = $this->makeWorld();

        $this->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items'         => [['menu_item_id' => $items['pizza']->id, 'quantity' => 1]],
            'address'       => '123 Test Street',
        ])->assertStatus(401);
    }

    public function test_customer_can_create_payment_intent(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();

        $response = $this->actingAs($customer, 'api')->postJson('/api/orders/payment-intent', [
            'restaurant_id' => $restaurant->id,
            'items'         => [
                ['menu_item_id' => $items['pizza']->id, 'quantity' => 1],
            ],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'payment_intent_id',
                'client_secret',
                'amount',
                'currency',
            ])
            ->assertJsonPath('payment_intent_id', 'pi_mock_12345')
            ->assertJsonPath('amount', 12); // 10.00 pizza + 2.00 delivery
    }

    public function test_customer_can_place_order_with_valid_payment_intent(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();

        $response = $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items'         => [
                ['menu_item_id' => $items['pizza']->id, 'quantity' => 1],
            ],
            'address' => '123 Test Street',
            'payment_intent_id' => 'pi_mock_12345',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_intent_id', 'pi_mock_12345');

        $this->assertDatabaseHas('orders', [
            'customer_id' => $customer->id,
            'payment_intent_id' => 'pi_mock_12345',
        ]);
    }

    public function test_customer_cannot_use_duplicate_payment_intent(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();

        // Place first order with pi_mock_12345
        $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items'         => [
                ['menu_item_id' => $items['pizza']->id, 'quantity' => 1],
            ],
            'address' => '123 First Street',
            'payment_intent_id' => 'pi_mock_12345',
        ])->assertCreated();

        // Place second order with same pi_mock_12345
        $response = $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items'         => [
                ['menu_item_id' => $items['pizza']->id, 'quantity' => 1],
            ],
            'address' => '123 Second Street',
            'payment_intent_id' => 'pi_mock_12345',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_intent_id']);
    }

    public function test_customer_cannot_place_order_with_failed_payment_intent(): void
    {
        [$customer, $restaurant, $items] = $this->makeWorld();

        $response = $this->actingAs($customer, 'api')->postJson('/api/orders', [
            'restaurant_id' => $restaurant->id,
            'items'         => [
                ['menu_item_id' => $items['pizza']->id, 'quantity' => 1],
            ],
            'address' => '123 Test Street',
            'payment_intent_id' => 'pi_mock_fail',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_intent_id']);
    }
}
