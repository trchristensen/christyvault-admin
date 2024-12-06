<?php

namespace Database\Factories;

use App\Models\InventoryTransaction;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryTransactionFactory extends Factory
{
    protected $model = InventoryTransaction::class;

    public function definition(): array
    {
        return [
            'inventory_item_id' => InventoryItem::factory(),
            'transaction_type' => fake()->randomElement([
                InventoryTransaction::TYPE_REORDER,
                InventoryTransaction::TYPE_RECEIPT,
                InventoryTransaction::TYPE_CONSUMPTION,
            ]),
            'quantity' => fake()->randomFloat(2, 1, 100),
            'user_id' => User::factory(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}