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

        setPermissionsTeamId(null); // Global roles, no team association

        // Create roles based on the UserRoles enum
        $roles = UserRole::cases();
        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role->value, 'guard_name' => 'web', 'company_id' => null],
                ['display_name' => $role->label()]
            );
        }
    }
}
