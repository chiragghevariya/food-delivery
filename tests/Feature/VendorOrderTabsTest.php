<?php

namespace Tests\Feature;

use App\Filament\Vendor\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VendorOrderTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('vendor'));
    }

    /** @return array<string, Order> one order per status, keyed by status */
    private function seedOneOrderPerStatus(Restaurant $rest, User $cust): array
    {
        $orders = [];
        foreach (['pending', 'accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'delivered', 'cancelled'] as $s) {
            $orders[$s] = Order::create([
                'customer_id' => $cust->id, 'restaurant_id' => $rest->id, 'status' => $s,
                'subtotal' => 10, 'delivery_fee' => 2, 'total' => 12, 'address' => 'A',
            ]);
        }
        return $orders;
    }

    public function test_each_tab_renders_and_filters_correctly(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::create(['name' => 'O', 'email' => 'o@t.test', 'password' => 'x', 'phone' => 'o', 'role' => 'restaurant_owner', 'is_active' => true]);
        $owner->assignRole('restaurant_owner');
        $rest = Restaurant::create(['user_id' => $owner->id, 'name' => 'R', 'address' => 'X']);
        $cust = User::create(['name' => 'C', 'email' => 'c@t.test', 'password' => 'x', 'phone' => 'c', 'role' => 'customer', 'is_active' => true]);
        $o = $this->seedOneOrderPerStatus($rest, $cust);

        $this->actingAs($owner);

        // Every tab must render (this is what was crashing) AND scope to the right rows.
        $expected = [
            'all'        => array_values($o),
            'new'        => [$o['pending']],
            'active'     => [$o['accepted'], $o['preparing'], $o['ready_for_pickup']],
            'on_the_way' => [$o['picked_up']],
            'completed'  => [$o['delivered'], $o['cancelled']],
        ];

        foreach ($expected as $tab => $shouldSee) {
            $component = Livewire::test(ListOrders::class, ['activeTab' => $tab])
                ->assertOk()
                ->assertCanSeeTableRecords($shouldSee);

            // also assert rows NOT in this tab are hidden
            $hidden = array_filter($o, fn (Order $ord) => ! in_array($ord->id, array_map(fn ($r) => $r->id, $shouldSee), true));
            if ($hidden) {
                $component->assertCanNotSeeTableRecords(array_values($hidden));
            }
        }
    }
}
