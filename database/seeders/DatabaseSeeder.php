<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            OrderSeeder::class,
            // Add any other seeders you have here
            ProductSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Todd',
            'email' => 'tchristensen@christyvault.com',
            'password' => bcrypt('password')
        ]);
    }
}
