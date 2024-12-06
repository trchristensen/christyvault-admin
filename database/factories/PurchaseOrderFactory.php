<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    public function definition(): array
    {
        $orderDate = fake()->dateTimeThisYear();
        
        return [
            'supplier_id' => Supplier::factory(),
            'status' => fake()->randomElement([
                PurchaseOrder::STATUS_DRAFT,
                PurchaseOrder::STATUS_SUBMITTED,
                PurchaseOrder::STATUS_RECEIVED,
                PurchaseOrder::STATUS_CANCELLED,
            ]),
            'order_date' => $orderDate,
            'expected_delivery_date' => fake()->dateTimeBetween($orderDate, '+30 days'),
            'received_date' => null,
            'total_amount' => fake()->randomFloat(2, 100, 10000),
            'notes' => fake()->optional()->sentence(),
            'created_by_user_id' => User::factory(),
        ];
    }
}