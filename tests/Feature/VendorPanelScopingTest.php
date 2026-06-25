<?php

namespace Tests\Feature;

use App\Filament\Vendor\Resources\MenuItemResource\Pages\CreateMenuItem;
use App\Filament\Vendor\Resources\MenuItemResource\Pages\ListMenuItems;
use App\Filament\Vendor\Resources\OrderResource\Pages\ListOrders;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VendorPanelScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // HTTP requests pick the panel via middleware; Livewire::test() does not,
        // so make the vendor panel current for these component-level tests.
        Filament::setCurrentPanel(Filament::getPanel('vendor'));
    }

    private function makeOwnerWithData(string $email, string $restName): array
    {
        $owner = User::create([
            'name' => $restName . ' Owner', 'email' => $email, 'password' => 'x',
            'phone' => 'owner-' . $email, 'role' => 'restaurant_owner', 'is_active' => true,
        ]);
        $owner->assignRole('restaurant_owner');

        $restaurant = Restaurant::create(['user_id' => $owner->id, 'name' => $restName, 'address' => 'X']);
        $cat = Category::firstOrCreate(['name' => 'Pizza']);
        $item = MenuItem::create(['restaurant_id' => $restaurant->id, 'category_id' => $cat->id, 'name' => $restName . ' Special', 'price' => 9.99]);

        $customer = User::create(['name' => 'C', 'email' => 'c' . $email, 'password' => 'x', 'phone' => 'cust-' . $email, 'role' => 'customer', 'is_active' => true]);
        $order = Order::create([
            'customer_id' => $customer->id, 'restaurant_id' => $restaurant->id,
            'status' => 'pending', 'subtotal' => 9.99, 'delivery_fee' => 2.5, 'total' => 12.49, 'address' => 'A',
        ]);

        return compact('owner', 'restaurant', 'item', 'order');
    }

    public function test_owner_only_sees_own_menu_items_and_orders(): void
    {
        $this->seed(RoleSeeder::class);
        $a = $this->makeOwnerWithData('a@t.test', 'Alpha');
        $b = $this->makeOwnerWithData('b@t.test', 'Bravo');

        $this->actingAs($a['owner']);

        Livewire::test(ListMenuItems::class)
            ->assertCanSeeTableRecords([$a['item']])
            ->assertCanNotSeeTableRecords([$b['item']]);

        Livewire::test(ListOrders::class)
            ->assertCanSeeTableRecords([$a['order']])
            ->assertCanNotSeeTableRecords([$b['order']]);

        $this->get("/dashboard/orders/{$b['order']->id}")->assertNotFound();
        $this->get("/dashboard/orders/{$a['order']->id}")->assertSuccessful();
    }

    public function test_create_stamps_owning_restaurant_id(): void
    {
        $this->seed(RoleSeeder::class);
        $a = $this->makeOwnerWithData('a@t.test', 'Alpha');
        $this->actingAs($a['owner']);

        $this->get('/dashboard/menu-items/create')->assertSuccessful();

        Livewire::test(CreateMenuItem::class)
            ->fillForm(['name' => 'New Dish', 'price' => 7.50, 'is_available' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('menu_items', [
            'name' => 'New Dish',
            'restaurant_id' => $a['restaurant']->id,
        ]);
    }

    public function test_owner_can_advance_order_status(): void
    {
        $this->seed(RoleSeeder::class);
        $a = $this->makeOwnerWithData('a@t.test', 'Alpha');
        $this->actingAs($a['owner']);

        Livewire::test(ListOrders::class)
            ->callTableAction('accept', $a['order']);

        $this->assertSame('accepted', $a['order']->refresh()->status->value);
        $this->assertDatabaseHas('order_status_history', [
            'order_id' => $a['order']->id,
            'status' => 'accepted',
        ]);
    }

    public function test_owner_pages_render(): void
    {
        $this->seed(RoleSeeder::class);
        $a = $this->makeOwnerWithData('a@t.test', 'Alpha');
        $this->actingAs($a['owner']);

        foreach (['/dashboard', '/dashboard/menu-items', '/dashboard/orders', '/dashboard/restaurant-profile', '/dashboard/earnings'] as $url) {
            $this->get($url)->assertSuccessful();
        }
    }
}
