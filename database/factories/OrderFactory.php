<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'order_number' => 'ORD-' . $this->faker->unique()->numberBetween(1000, 9999),
            'requested_delivery_date' => $this->faker->dateTimeBetween('+1 week', '+2 months'),
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'in_production', 'ready_for_delivery', 'out_for_delivery', 'delivered']),
            'special_instructions' => $this->faker->optional()->sentence,
        ];
    }
}
