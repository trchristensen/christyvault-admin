<?php

namespace Database\Factories;

use App\Models\KanbanCard;
use Illuminate\Database\Eloquent\Factories\Factory;

class KanbanCardFactory extends Factory
{
    protected $model = KanbanCard::class;

    public function definition(): array
    {
        return [
            'inventory_item_id' => null,
            'bin_number' => fake()->bothify('BIN-####'),
            'bin_location' => fake()->bothify('ZONE-#-RACK-##'),
            'reorder_point' => fake()->numberBetween(5, 50),
            'status' => fake()->randomElement([
                KanbanCard::STATUS_ACTIVE,
                KanbanCard::STATUS_PENDING_ORDER,
                KanbanCard::STATUS_ORDERED,
            ]),
            'last_scanned_at' => fake()->optional()->dateTimeThisMonth(),
            'scanned_by_user_id' => null,
        ];
    }
}