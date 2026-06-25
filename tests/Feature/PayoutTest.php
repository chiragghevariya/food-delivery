<?php

namespace Tests\Feature;

use App\Enums\PayoutStatus;
use App\Filament\Admin\Resources\PayoutResource\Pages\ListPayouts as AdminListPayouts;
use App\Filament\Vendor\Resources\PayoutResource\Pages\ListPayouts as VendorListPayouts;
use App\Models\Order;
use App\Models\Payout;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayoutService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PayoutTest extends TestCase
{
    use RefreshDatabase;

    private function restaurantWithDeliveredOrders(string $email, array $subtotals): array
    {
        $owner = User::create(['name' => 'O', 'email' => $email, 'password' => 'x', 'phone' => 'o' . $email, 'role' => 'restaurant_owner', 'is_active' => true]);
        $owner->assignRole('restaurant_owner');
        $rest = Restaurant::create(['user_id' => $owner->id, 'name' => 'R' . $email, 'address' => 'X']);
        $cust = User::create(['name' => 'C', 'email' => 'c' . $email, 'password' => 'x', 'phone' => 'c' . $email, 'role' => 'customer', 'is_active' => true]);
        foreach ($subtotals as $sub) {
            Order::create([
                'customer_id' => $cust->id, 'restaurant_id' => $rest->id, 'status' => 'delivered',
                'subtotal' => $sub, 'delivery_fee' => 2, 'total' => $sub + 2, 'address' => 'A',
            ]);
        }
        return compact('owner', 'rest');
    }

    public function test_generation_computes_commission_and_net(): void
    {
        $this->seed(RoleSeeder::class);
        Setting::set('platform_commission_rate', 15, 'float');
        $r = $this->restaurantWithDeliveredOrders('a@t.test', [100, 50]); // gross 150

        $payouts = app(PayoutService::class)->generateRestaurantPayouts();

        $this->assertCount(1, $payouts);
        $p = $payouts->first();
        $this->assertEquals(150.00, (float) $p->gross_amount);
        $this->assertEquals(22.50, (float) $p->commission_amount); // 15% of 150
        $this->assertEquals(127.50, (float) $p->net_amount);
        $this->assertEquals(2, $p->orders_count);
        $this->assertEquals(PayoutStatus::PENDING, $p->status);

        // Orders are now stamped as settled.
        $this->assertEquals(0, Order::where('restaurant_id', $r['rest']->id)->whereNull('restaurant_payout_id')->count());
    }

    public function test_generation_is_idempotent(): void
    {
        $this->seed(RoleSeeder::class);
        $this->restaurantWithDeliveredOrders('a@t.test', [100, 50]);

        $svc = app(PayoutService::class);
        $first = $svc->generateRestaurantPayouts();
        $second = $svc->generateRestaurantPayouts(); // nothing left unsettled

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertEquals(1, Payout::count());
    }

    public function test_new_orders_after_settlement_create_a_second_payout(): void
    {
        $this->seed(RoleSeeder::class);
        $r = $this->restaurantWithDeliveredOrders('a@t.test', [100]);
        $svc = app(PayoutService::class);
        $svc->generateRestaurantPayouts();

        // A new delivered order arrives later.
        Order::create([
            'customer_id' => User::where('role', 'customer')->first()->id,
            'restaurant_id' => $r['rest']->id, 'status' => 'delivered',
            'subtotal' => 40, 'delivery_fee' => 2, 'total' => 42, 'address' => 'A',
        ]);

        $second = $svc->generateRestaurantPayouts();
        $this->assertCount(1, $second);
        $this->assertEquals(40.00, (float) $second->first()->gross_amount);
        $this->assertEquals(2, Payout::count());
    }

    public function test_mark_paid_sets_status_and_timestamp(): void
    {
        $this->seed(RoleSeeder::class);
        $this->restaurantWithDeliveredOrders('a@t.test', [100]);
        $payout = app(PayoutService::class)->generateRestaurantPayouts()->first();

        $payout->markPaid('TXN-123');

        $payout->refresh();
        $this->assertEquals(PayoutStatus::PAID, $payout->status);
        $this->assertNotNull($payout->paid_at);
        $this->assertEquals('TXN-123', $payout->reference);
    }

    public function test_vendor_only_sees_own_restaurant_payouts(): void
    {
        $this->seed(RoleSeeder::class);
        $a = $this->restaurantWithDeliveredOrders('a@t.test', [100]);
        $b = $this->restaurantWithDeliveredOrders('b@t.test', [200]);
        $svc = app(PayoutService::class);
        $svc->generateRestaurantPayouts();

        $aPayout = Payout::where('payee_id', $a['rest']->id)->first();
        $bPayout = Payout::where('payee_id', $b['rest']->id)->first();

        $this->actingAs($a['owner']);
        Filament::setCurrentPanel(Filament::getPanel('vendor'));

        Livewire::test(VendorListPayouts::class)
            ->assertCanSeeTableRecords([$aPayout])
            ->assertCanNotSeeTableRecords([$bPayout]);
    }

    public function test_admin_can_export_csv(): void
    {
        $this->seed(RoleSeeder::class);
        $this->restaurantWithDeliveredOrders('a@t.test', [100]);
        app(PayoutService::class)->generateRestaurantPayouts();

        $admin = User::create(['name' => 'A', 'email' => 'admin@t.test', 'password' => 'x', 'phone' => 'adm', 'role' => 'admin', 'is_active' => true]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $response = Livewire::test(AdminListPayouts::class)
            ->callTableAction("export");

        $response->assertSuccessful();
    }
}
