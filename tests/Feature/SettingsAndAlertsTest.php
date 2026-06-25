<?php

namespace Tests\Feature;

use App\Filament\Admin\Pages\ManageSettings;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_settings(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::create(['name' => 'A', 'email' => 'admin@t.test', 'password' => 'x', 'phone' => 'a', 'role' => 'admin', 'is_active' => true]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'platform_commission_rate' => 20,
                'default_delivery_fee' => 3.00,
                'min_order_value' => 8.00,
                'currency' => 'EUR',
                'currency_symbol' => '€',
                'maintenance_mode' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(20.0, Setting::get('platform_commission_rate'));
        $this->assertEquals('€', Setting::get('currency_symbol'));
        $this->assertTrue(Setting::get('maintenance_mode'));
    }

    public function test_new_pending_order_notifies_owner(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::create(['name' => 'O', 'email' => 'o@t.test', 'password' => 'x', 'phone' => 'o', 'role' => 'restaurant_owner', 'is_active' => true]);
        $rest = Restaurant::create(['user_id' => $owner->id, 'name' => 'R', 'address' => 'X']);
        $cust = User::create(['name' => 'C', 'email' => 'c@t.test', 'password' => 'x', 'phone' => 'c', 'role' => 'customer', 'is_active' => true]);

        Order::create([
            'customer_id' => $cust->id, 'restaurant_id' => $rest->id, 'status' => 'pending',
            'subtotal' => 10, 'delivery_fee' => 2, 'total' => 12, 'address' => 'A',
        ]);

        $this->assertEquals(1, $owner->notifications()->count());
        $this->assertStringContainsString('New order', $owner->notifications()->first()->data['title'] ?? json_encode($owner->notifications()->first()->data));
    }

    public function test_non_pending_order_does_not_notify(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::create(['name' => 'O', 'email' => 'o@t.test', 'password' => 'x', 'phone' => 'o', 'role' => 'restaurant_owner', 'is_active' => true]);
        $rest = Restaurant::create(['user_id' => $owner->id, 'name' => 'R', 'address' => 'X']);
        $cust = User::create(['name' => 'C', 'email' => 'c@t.test', 'password' => 'x', 'phone' => 'c', 'role' => 'customer', 'is_active' => true]);

        Order::create([
            'customer_id' => $cust->id, 'restaurant_id' => $rest->id, 'status' => 'delivered',
            'subtotal' => 10, 'delivery_fee' => 2, 'total' => 12, 'address' => 'A',
        ]);

        $this->assertEquals(0, $owner->notifications()->count());
    }
}
