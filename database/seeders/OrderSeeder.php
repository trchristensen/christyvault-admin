<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderSeeder extends Seeder
{
    public function run()
    {
        // Clear existing orders
        DB::table('orders')->delete();

        // Create new orders
        Order::factory()
            ->count(10)
            ->create();
    }
}
