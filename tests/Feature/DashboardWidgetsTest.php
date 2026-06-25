<?php

namespace Tests\Feature;

use App\Filament\Admin\Widgets\AdminStatsOverview;
use App\Filament\Admin\Widgets\OrderStatusChart;
use App\Filament\Admin\Widgets\OrdersRevenueChart;
use App\Filament\Vendor\Widgets\TopSellingItems;
use App\Filament\Vendor\Widgets\VendorRevenueChart;
use App\Filament\Vendor\Widgets\VendorStatsOverview;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::create(['name' => 'A', 'email' => 'admin@t.test', 'password' => 'x', 'phone' => 'a', 'role' => 'admin', 'is_active' => true]);
        $u->assignRole('admin');
        return $u;
    }

    private function ownerWithOrders(string $email, int $ordersToday): array
    {
        $owner = User::create(['name' => 'O', 'email' => $email, 'password' => 'x', 'phone' => 'o' . $email, 'role' => 'restaurant_owner', 'is_active' => true]);
        $owner->assignRole('restaurant_owner');
        $rest = Restaurant::create(['user_id' => $owner->id, 'name' => 'R' . $email, 'address' => 'X']);
        $cust = User::create(['name' => 'C', 'email' => 'c' . $email, 'password' => 'x', 'phone' => 'c' . $email, 'role' => 'customer', 'is_active' => true]);
        for ($i = 0; $i < $ordersToday; $i++) {
            Order::create([
                'customer_id' => $cust->id, 'restaurant_id' => $rest->id, 'status' => 'pending',
                'subtotal' => 10, 'delivery_fee' => 2, 'total' => 12, 'address' => 'A',
            ]);
        }
        return compact('owner', 'rest');
    }

    public function test_widgets_are_registered_on_panels(): void
    {
        $this->seed(RoleSeeder::class);

        $adminWidgets = Filament::getPanel('admin')->getWidgets();
        $this->assertContains(AdminStatsOverview::class, $adminWidgets);
        $this->assertContains(OrdersRevenueChart::class, $adminWidgets);
        $this->assertContains(OrderStatusChart::class, $adminWidgets);

        $vendorWidgets = Filament::getPanel('vendor')->getWidgets();
        $this->assertContains(VendorStatsOverview::class, $vendorWidgets);
        $this->assertContains(VendorRevenueChart::class, $vendorWidgets);
        $this->assertContains(TopSellingItems::class, $vendorWidgets);
    }

    public function test_admin_widgets_render(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AdminStatsOverview::class)->assertOk();
        Livewire::test(OrdersRevenueChart::class)->assertOk();
        Livewire::test(OrderStatusChart::class)->assertOk();
    }

    public function test_vendor_widgets_render_and_are_scoped(): void
    {
        $this->seed(RoleSeeder::class);
        $a = $this->ownerWithOrders('a@t.test', ordersToday: 2);
        $this->ownerWithOrders('b@t.test', ordersToday: 5); // other restaurant, must be ignored

        $this->actingAs($a['owner']);
        Filament::setCurrentPanel(Filament::getPanel('vendor'));

        // Renders without error.
        Livewire::test(VendorStatsOverview::class)->assertOk();
        Livewire::test(VendorRevenueChart::class)->assertOk();
        Livewire::test(TopSellingItems::class)->assertOk();

        // Scoping: "Today's Orders" reflects ONLY owner A's 2 orders, not B's 5.
        $widget = new VendorStatsOverview();
        $stats = (function () { return $this->getStats(); })->call($widget);
        $this->assertSame('2', $stats[0]->getValue(), "Today's Orders should be scoped to the owner");
    }
}
