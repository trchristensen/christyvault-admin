<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create User
        $user = User::create([
            'name' => 'Jeff Gietman',
            'email' => 'jeff@christyvault.com',
            'password' => Hash::make('password'),
        ]);

        // Create Employee linked to User
        $employee = Employee::create([
            'user_id' => $user->id,
            'name' => 'Jeff Gietman',
            'email' => 'jeff@christyvault.com',
            'phone' => '(555) 123-4567',
            'position' => 'driver',
            'is_active' => true,
            'christy_location' => 'colma',
            'hire_date' => now(),
            'birth_date' => '1980-01-01',
            'address' => '123 Main St, Anytown, USA',
        ]);

        // Create Driver record linked to Employee
        Driver::create([
            'employee_id' => $employee->id,
            // 'license_number' => $employee->license_number,
            // 'license_expiration' => $employee->license_expiration,
            'notes' => 'Created by seeder',
        ]);

        // Assign roles if needed
        // $user->assignRole('driver');
    }
}
