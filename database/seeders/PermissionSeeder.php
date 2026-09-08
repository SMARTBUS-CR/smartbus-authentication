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

        $permissions = [
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',
            'companies.manage',
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.assign-roles',
            'users.assign-permissions',
            'roles.view',
            'roles.manage',
            'permissions.view',
            'permissions.manage',
        ];

        $permissionModels = [];
        foreach ($permissions as $permission) {
            $permissionModels[] = Permission::findOrCreate($permission, 'web');
        }

        Role::findByName(UserRole::SUPER_ADMIN->value, 'web')
            ->syncPermissions($permissionModels);

        Role::findByName(UserRole::COMPANY_ADMIN->value, 'web')
            ->syncPermissions([
                'users.view',
                'users.create',
                'users.update',
                'users.delete',
                'users.assign-roles',
                'users.assign-permissions',
                'companies.view',
                'companies.create',
                'companies.update',
                'companies.delete',
                'companies.manage',
                'roles.view',
                'permissions.view',
            ]);
    }
}
