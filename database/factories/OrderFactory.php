<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'order_number' => Str::uuid(),
            'requested_delivery_date' => $this->faker->dateTimeBetween('+1 week', '+2 months'),
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'in_production', 'ready_for_delivery', 'out_for_delivery', 'delivered']),
            'special_instructions' => $this->faker->optional()->sentence,
            'uuid' => $this->faker->uuid(),
        ];
    }
}
