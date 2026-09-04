<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [UserRole::SuperAdmin, 'Super Admin', 'admin@superadmin.com', 'superadmin123'],
            [UserRole::CompanyAdmin, 'Company Admin', 'admin@company.com', 'companyadmin123'],
            [UserRole::Driver, 'Driver User', 'user@driver.com', 'driver123'],
            [UserRole::Passenger, 'Passenger User', 'user@passenger.com', 'passenger123'],
        ];

        foreach ($users as [$role, $name, $email, $password]) {
            $user = User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]);

            $user->syncRoles($role);
        }
    }
}
