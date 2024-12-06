<?php

namespace Database\Seeders;

use App\Models\InventoryTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $inventoryItems = DB::table('inventory_items')->pluck('id');
        $users = DB::table('users')->pluck('id');
        
        // Create 100 random transactions
        foreach (range(1, 100) as $index) {
            DB::table('inventory_transactions')->insert([
                'inventory_item_id' => $inventoryItems->random(),
                'transaction_type' => fake()->randomElement([
                    InventoryTransaction::TYPE_REORDER,
                    InventoryTransaction::TYPE_RECEIPT,
                    InventoryTransaction::TYPE_CONSUMPTION,
                ]),
                'quantity' => fake()->randomFloat(2, 1, 100),
                'user_id' => $users->random(),
                'notes' => fake()->optional()->sentence(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}