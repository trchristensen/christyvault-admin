<?php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            ['name' => 'driver', 'display_name' => 'Driver'],
            ['name' => 'production', 'display_name' => 'Production'],
            ['name' => 'foreman', 'display_name' => 'Foreman'],
            ['name' => 'manager', 'display_name' => 'Manager'],
        ];

        foreach ($positions as $position) {
            Position::firstOrCreate(
                ['name' => $position['name']],
                $position
            );
        }
    }
}