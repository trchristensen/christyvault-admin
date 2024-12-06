<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use Database\Factories\InventoryItemFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryItemSeeder extends Seeder
{
    public function run(): void
    {
        $factory = new InventoryItemFactory();
        
        foreach(range(1, 50) as $index) {
            $data = $factory->definition();
            
            DB::table('inventory_items')->insert([
                'sku' => $data['sku'],
                'name' => $data['name'],
                'description' => $data['description'],
                'category' => $data['category'],
                'unit_of_measure' => $data['unit_of_measure'],
                'minimum_stock' => $data['minimum_stock'],
                'current_stock' => $data['current_stock'],
                'reorder_lead_time' => $data['reorder_lead_time'],
                'storage_location' => $data['storage_location'],
                'qr_code' => $data['qr_code'],
                'active' => DB::raw($data['active'] ? 'true' : 'false'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}