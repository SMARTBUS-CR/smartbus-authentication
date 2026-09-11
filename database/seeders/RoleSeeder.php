<?php

namespace Database\Seeders;

use App\Enums\UserRole;
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

        // Create roles based on the UserRoles enum
        $roles = UserRole::cases();
        foreach ($roles as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }
}
