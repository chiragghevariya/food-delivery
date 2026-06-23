<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_it_seeds_the_expected_demo_data(): void
    {
        $this->seed(DemoSeeder::class);

        // 3 restaurants, each with 5-8 menu items
        $this->assertSame(3, Restaurant::count());
        foreach (Restaurant::all() as $restaurant) {
            $count = $restaurant->menuItems()->count();
            $this->assertGreaterThanOrEqual(5, $count);
            $this->assertLessThanOrEqual(8, $count);
        }

        // test accounts exist with the right roles
        $customer = User::where('email', 'customer1@demo.com')->first();
        $rider = User::where('email', 'rider1@demo.com')->first();
        $this->assertNotNull($customer);
        $this->assertNotNull($rider);
        $this->assertTrue($customer->hasRole('customer'));
        $this->assertTrue($rider->hasRole('rider'));

        // 5 completed orders, each delivered and with a 6-step status history
        $this->assertSame(5, Order::count());
        $this->assertSame(5, Order::where('status', 'delivered')->count());
        foreach (Order::all() as $order) {
            $this->assertSame(6, $order->statusHistory()->count());
            $this->assertGreaterThan(0, $order->items()->count());
            // total should equal subtotal + the $2 delivery fee
            $this->assertEqualsWithDelta(
                (float) $order->subtotal + 2,
                (float) $order->total,
                0.001
            );
        }
    }

    public function test_running_it_twice_does_not_duplicate(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(3, Restaurant::count());
        $this->assertSame(5, Order::count());
        $this->assertSame(1, User::where('email', 'customer1@demo.com')->count());
    }
}
