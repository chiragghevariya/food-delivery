<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Create the 4 user roles for the app.
     */
    public function run(): void
    {
        // We use the "api" guard because users authenticate through the JWT api guard.
        $guard = 'api';

        foreach (['customer', 'rider', 'restaurant_owner', 'admin'] as $role) {
            // firstOrCreate: only creates the role if it does not exist yet
            Role::firstOrCreate(['name' => $role, 'guard_name' => $guard]);
        }
    }
}
