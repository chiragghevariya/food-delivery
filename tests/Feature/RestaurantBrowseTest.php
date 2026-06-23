<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the public restaurant + menu browsing endpoints (Phase 2).
 */
class RestaurantBrowseTest extends TestCase
{
    use RefreshDatabase;

    /** Create a restaurant owned by a fresh owner user. */
    private function makeRestaurant(array $attributes = []): Restaurant
    {
        $owner = User::factory()->create(['role' => 'restaurant_owner']);

        return Restaurant::create(array_merge([
            'user_id'     => $owner->id,
            'name'        => 'Test Restaurant',
            'description' => 'Tasty food',
            'address'     => '123 Street',
            'lat'         => 24.8600,
            'lng'         => 67.0000,
            'is_open'     => true,
            'rating'      => 4.5,
        ], $attributes));
    }

    public function test_it_lists_restaurants(): void
    {
        $this->makeRestaurant(['name' => 'Pizza Place']);
        $this->makeRestaurant(['name' => 'Burger Hub']);

        $this->getJson('/api/restaurants')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_it_can_search_restaurants_by_name(): void
    {
        $this->makeRestaurant(['name' => 'Pizza Place']);
        $this->makeRestaurant(['name' => 'Burger Hub']);

        $this->getJson('/api/restaurants?search=pizza')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Pizza Place');
    }

    public function test_it_can_filter_restaurants_by_category(): void
    {
        $drinks = Category::create(['name' => 'Drinks']);

        $withDrinks = $this->makeRestaurant(['name' => 'Has Drinks']);
        MenuItem::create([
            'restaurant_id' => $withDrinks->id,
            'category_id'   => $drinks->id,
            'name'          => 'Coke',
            'price'         => 2.00,
            'is_available'  => true,
        ]);

        $this->makeRestaurant(['name' => 'No Drinks']); // no items

        $this->getJson("/api/restaurants?category={$drinks->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Has Drinks');
    }

    public function test_nearby_returns_only_restaurants_within_radius(): void
    {
        // very close to (24.86, 67.00) — a few hundred metres away
        $near = $this->makeRestaurant(['name' => 'Near Cafe', 'lat' => 24.8605, 'lng' => 67.0005]);
        // far away (different city) — well outside 5 km
        $this->makeRestaurant(['name' => 'Far Diner', 'lat' => 25.5000, 'lng' => 68.0000]);

        $response = $this->getJson('/api/restaurants/nearby?lat=24.86&lng=67.00&radius=5')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Near Cafe');

        // the distance is included in the response
        $this->assertArrayHasKey('distance_km', $response->json('data.0'));
    }

    public function test_it_shows_a_restaurant_with_its_menu(): void
    {
        $restaurant = $this->makeRestaurant();
        MenuItem::create([
            'restaurant_id' => $restaurant->id,
            'name'          => 'Margherita',
            'price'         => 9.99,
            'is_available'  => true,
        ]);

        $this->getJson("/api/restaurants/{$restaurant->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $restaurant->id)
            ->assertJsonCount(1, 'data.menu_items');
    }

    public function test_menu_index_lists_available_items_only(): void
    {
        $restaurant = $this->makeRestaurant();
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Available', 'price' => 5, 'is_available' => true]);
        MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Hidden',    'price' => 5, 'is_available' => false]);

        $this->getJson("/api/restaurants/{$restaurant->id}/menu")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Available');
    }

    public function test_it_shows_a_single_menu_item(): void
    {
        $restaurant = $this->makeRestaurant();
        $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'name' => 'Fries', 'price' => 3.50, 'is_available' => true]);

        $this->getJson("/api/menu-items/{$item->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.name', 'Fries');
    }
}
