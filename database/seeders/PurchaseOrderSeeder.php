<?php

namespace Database\Seeders;

use Database\Factories\PurchaseOrderFactory;
use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\PurchaseOrder;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = DB::table('suppliers')->pluck('id');
        $users = DB::table('users')->pluck('id');
        
        // Create 20 purchase orders
        foreach (range(1, 20) as $index) {
            $supplier_id = $suppliers->random();
            
            $po_id = DB::table('purchase_orders')->insertGetId([
                'supplier_id' => $supplier_id,
                'status' => fake()->randomElement([
                    PurchaseOrder::STATUS_DRAFT,
                    PurchaseOrder::STATUS_SUBMITTED,
                    PurchaseOrder::STATUS_RECEIVED,
                    PurchaseOrder::STATUS_CANCELLED,
                ]),
                'order_date' => now(),
                'expected_delivery_date' => now()->addDays(rand(1, 30)),
                'received_date' => null,
                'total_amount' => 0, // Will update after adding items
                'notes' => fake()->optional()->sentence(),
                'created_by_user_id' => $users->random(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            // Get inventory items for this supplier
            $supplierItems = DB::table('inventory_item_suppliers')
                ->where('supplier_id', $supplier_id)
                ->pluck('inventory_item_id');
            
            if ($supplierItems->isEmpty()) {
                continue;
            }

            // Add 1-5 items to each purchase order
            $numItems = min(rand(1, 5), $supplierItems->count());
            $total_amount = 0;
            
            foreach ($supplierItems->random($numItems) as $item_id) {
                $quantity = rand(1, 100);
                $unit_price = fake()->randomFloat(2, 1, 1000);
                $total_price = $quantity * $unit_price;
                
                DB::table('purchase_order_items')->insert([
                    'purchase_order_id' => $po_id,
                    'inventory_item_id' => $item_id,
                    'supplier_id' => $supplier_id,
                    'supplier_sku' => strtoupper(fake()->bothify('SUP??###')),
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'total_price' => $total_price,
                    'received_quantity' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                $total_amount += $total_price;
            }
            
            // Update purchase order total amount
            DB::table('purchase_orders')
                ->where('id', $po_id)
                ->update(['total_amount' => $total_amount]);
        }
    }
}