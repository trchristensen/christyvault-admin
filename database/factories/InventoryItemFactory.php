<?php

namespace Database\Factories;

use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('??###')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => fake()->randomElement(['Raw Materials', 'Packaging', 'Finished Goods', 'Supplies']),
            'unit_of_measure' => fake()->randomElement(['EA', 'KG', 'L', 'M', 'BOX', 'ROLL']),
            'minimum_stock' => fake()->numberBetween(10, 100),
            'current_stock' => fake()->numberBetween(0, 200),
            'reorder_lead_time' => fake()->numberBetween(1, 30),
            'storage_location' => fake()->bothify('AISLE-##-SHELF-##'),
            'qr_code' => null,
            'active' => fake()->boolean(90),
        ];
    }
}