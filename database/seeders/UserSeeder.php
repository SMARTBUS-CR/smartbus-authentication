<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $users = [
            ['Super Admin', 'admin@superadmin.com', 'superadmin123', UserRole::SuperAdmin],
            ['Company Admin', 'admin@company.com', 'companyadmin123', null],
            ['Driver User', 'user@driver.com', 'driver123', null],
            ['Passenger User', 'user@passenger.com', 'passenger123', null],
        ];

        foreach ($users as [$name, $email, $password, $role]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            if ($role === UserRole::SuperAdmin) {
                $user->syncRoles([$role->value]);
            }
        }
    }
}
