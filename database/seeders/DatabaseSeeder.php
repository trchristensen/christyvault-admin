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
            // OrderSeeder::class,
            // Add any other seeders you have here
            LocationSeeder::class,
            // CustomerSeeder::class,
            ProductSeeder::class,
            PositionSeeder::class,

            // Operations

            // SupplierSeeder::class,
            // InventoryItemSeeder::class,
            // KanbanCardSeeder::class,
            // PurchaseOrderSeeder::class,
            // InventoryTransactionSeeder::class,
        ]);

        if (app()->environment('local') && env('LOCAL_ADMIN_PASSWORD')) {
            User::updateOrCreate(
                ['email' => env('LOCAL_ADMIN_EMAIL', 'tchristensen@christyvault.com')],
                [
                    'name' => env('LOCAL_ADMIN_NAME', 'Todd'),
                    'password' => bcrypt(env('LOCAL_ADMIN_PASSWORD')),
                ],
            );
        }
    }
}
