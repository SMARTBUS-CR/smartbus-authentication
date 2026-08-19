<?php

namespace Database\Seeders;

use App\Enums\UserRoles;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a new default Super Admin user with predefined credentials.
        User::factory()->withRole(UserRoles::SUPER_ADMIN)->create([
            'name' => 'Super Admin',
            'email' => 'admin@superadmin.com',
            'password' => Hash::make('superadmin123'),
        ]);

        // Create a new default Company Admin user with predefined credentials.
        User::factory()->withRole(UserRoles::COMPANY_ADMIN)->create([
            'name' => 'Company Admin',
            'email' => 'admin@company.com',
            'password'=> Hash::make('companyadmin123'),
        ]);

        // Create a new default Driver user with predefined credentials.
        User::factory()->withRole(UserRoles::DRIVER)->create([
            'name' => 'Driver User',
            'email' => 'user@driver.com',
            'password'=> Hash::make('driver123'),
        ]);
        
        // Create a new default Passenger user with predefined credentials.
        User::factory()->withRole(UserRoles::PASSENGER)->create([
            'name' => 'Passenger User',
            'email' => 'user@passenger.com',
            'password'=> Hash::make('passenger123'),
        ]);
    }
}
