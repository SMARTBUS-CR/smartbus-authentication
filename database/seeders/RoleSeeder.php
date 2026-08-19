<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear the Spatie cache before creating roles
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::create(['name' => 'super-admin']);    // Government
        Role::create(['name' => 'company-admin']);  // Business Owners
        Role::create(['name' => 'driver']);         // Drivers
        Role::create(['name' => 'passenger']);      // Passengers
    }
}
