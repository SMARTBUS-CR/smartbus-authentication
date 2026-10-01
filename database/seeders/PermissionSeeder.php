<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        setPermissionsTeamId(null); // Global permissions, no team association

        $permissions = [
            'ViewAny:Company',
            'View:Company',
            'Create:Company',
            'Update:Company',
            'Delete:Company',
            'DeleteAny:Company',
            'Restore:Company',
            'ForceDelete:Company',
            'ForceDeleteAny:Company',
            'RestoreAny:Company',
            'Replicate:Company',
            'Reorder:Company',
            'ViewAny:User',
            'View:User',
            'Create:User',
            'Update:User',
            'Delete:User',
            'DeleteAny:User',
            'Restore:User',
            'ForceDelete:User',
            'ForceDeleteAny:User',
            'RestoreAny:User',
            'Replicate:User',
            'Reorder:User',
            'ViewAny:Role',
            'View:Role',
            'Create:Role',
            'Update:Role',
            'Delete:Role',
            'DeleteAny:Role',
            'Restore:Role',
            'ForceDelete:Role',
            'ForceDeleteAny:Role',
            'RestoreAny:Role',
            'Replicate:Role',
            'Reorder:Role',
            'View:Dashboard',
        ];

        $permissionModels = [];
        foreach ($permissions as $permission) {
            $permissionModels[] = Permission::findOrCreate($permission, 'web');
        }

        Role::findByName(UserRole::SUPER_ADMIN->value, 'web')
            ->syncPermissions($permissionModels);

        Role::findByName(UserRole::ADMIN->value, 'web')
            ->syncPermissions(collect($permissions)
                ->filter(fn ($permission) => str_contains($permission, 'Dashboard'))
                ->toArray()
            );
    }
}
