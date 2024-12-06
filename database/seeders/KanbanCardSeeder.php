<?php

namespace Database\Seeders;

use App\Models\KanbanCard;
use App\Models\InventoryItem;
use App\Models\User;
use Database\Factories\KanbanCardFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KanbanCardSeeder extends Seeder
{
    public function run(): void
    {
        $factory = new KanbanCardFactory();
        $inventoryItems = DB::table('inventory_items')->pluck('id');
        $users = DB::table('users')->pluck('id');
        
        foreach($inventoryItems as $itemId) {
            $numCards = rand(1, 2);
            for ($i = 0; $i < $numCards; $i++) {
                $data = $factory->definition();
                
                DB::table('kanban_cards')->insert([
                    'inventory_item_id' => $itemId,
                    'bin_number' => $data['bin_number'],
                    'bin_location' => $data['bin_location'],
                    'reorder_point' => $data['reorder_point'],
                    'status' => $data['status'],
                    'last_scanned_at' => $data['last_scanned_at'],
                    'scanned_by_user_id' => $users->random(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}