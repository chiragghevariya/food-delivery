<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_render(): void
    {
        $this->seed(RoleSeeder::class);

        $admin = User::create([
            'name' => 'Test Admin', 'email' => 'test-admin@fooddelivery.test',
            'password' => 'password', 'phone' => '0', 'role' => 'admin', 'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        // Minimal order graph so the Order view + relation managers render.
        $owner = User::create(['name' => 'Owner', 'email' => 'o@t.test', 'password' => 'x', 'phone' => '1', 'role' => 'restaurant_owner', 'is_active' => true]);
        $customer = User::create(['name' => 'Cust', 'email' => 'c@t.test', 'password' => 'x', 'phone' => '2', 'role' => 'customer', 'is_active' => true]);
        $restaurant = Restaurant::create(['user_id' => $owner->id, 'name' => 'Testaurant', 'address' => 'X']);
        $cat = Category::create(['name' => 'Pizza']);
        $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'category_id' => $cat->id, 'name' => 'Margherita', 'price' => 9.99]);
        $order = Order::create([
            'customer_id' => $customer->id, 'restaurant_id' => $restaurant->id,
            'status' => 'pending', 'subtotal' => 9.99, 'delivery_fee' => 2.50, 'total' => 12.49, 'address' => '12 Main St',
        ]);
        OrderItem::create(['order_id' => $order->id, 'menu_item_id' => $item->id, 'quantity' => 1, 'unit_price' => 9.99, 'subtotal' => 9.99]);
        $order->statusHistory()->create(['status' => 'pending', 'note' => 'Order placed']);

        foreach ([
            '/admin', '/admin/categories', '/admin/restaurants', '/admin/users',
            '/admin/riders', '/admin/orders', '/admin/payouts',
            '/admin/categories/create', '/admin/restaurants/create', '/admin/users/create',
            "/admin/orders/{$order->id}",          // view page + infolist
        ] as $url) {
            $this->get($url)->assertSuccessful();
        }
    }
}
