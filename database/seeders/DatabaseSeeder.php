<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1) Create the 4 roles (customer, rider, restaurant_owner, admin).
        $this->call(RoleSeeder::class);

        // 1b) Create default food categories (used by the customer app's filter chips).
        $this->call(CategorySeeder::class);

        // 2) Create a default admin account you can log in with right away.
        $admin = User::firstOrCreate(
            ['email' => 'admin@fooddelivery.test'],
            [
                'name'      => 'Super Admin',
                'password'  => 'password',   // auto-hashed by the model's "hashed" cast
                'phone'     => '0000000000',
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        // Give the admin user the spatie "admin" role.
        $admin->assignRole('admin');

        // 3) Fill the app with demo data (restaurants, menus, test accounts, orders).
        $this->call(DemoSeeder::class);
    }
}
