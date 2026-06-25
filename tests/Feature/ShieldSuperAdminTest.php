<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ShieldSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bypasses_every_gate(): void
    {
        $this->seed(RoleSeeder::class);
        $admin = User::create(['name' => 'A', 'email' => 'admin@t.test', 'password' => 'x', 'phone' => 'a', 'role' => 'admin', 'is_active' => true]);
        $admin->assignRole('admin');

        // Abilities that have no policy/permission defined anywhere — the
        // super-admin Gate::before must still grant them.
        $this->assertTrue(Gate::forUser($admin)->allows('view_any_restaurant'));
        $this->assertTrue(Gate::forUser($admin)->allows('anything_at_all'));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', new Restaurant()));
    }

    public function test_non_admin_is_not_granted_blanket_access(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::create(['name' => 'O', 'email' => 'o@t.test', 'password' => 'x', 'phone' => 'o', 'role' => 'restaurant_owner', 'is_active' => true]);
        $owner->assignRole('restaurant_owner');

        // No super-admin bypass for non-admins; undefined abilities are denied.
        $this->assertFalse(Gate::forUser($owner)->allows('anything_at_all'));
    }

    public function test_panel_access_still_role_gated(): void
    {
        $this->seed(RoleSeeder::class);
        $owner = User::create(['name' => 'O', 'email' => 'o@t.test', 'password' => 'x', 'phone' => 'o', 'role' => 'restaurant_owner', 'is_active' => true]);
        $owner->assignRole('restaurant_owner');

        // Owner cannot reach the admin panel, admin-bypass or not.
        $this->actingAs($owner);
        $this->get('/admin')->assertForbidden();
    }
}
