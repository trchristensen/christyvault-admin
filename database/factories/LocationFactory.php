<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'address_line1' => fake()->streetAddress(),
            'address_line2' => null,
            'city' => fake()->city(),
            'state' => 'CA',
            'postal_code' => fake()->postcode(),
            'location_type' => 'cemetery',
            'latitude' => fake()->latitude(36.0, 38.0),
            'longitude' => fake()->longitude(-123.0, -121.0),
        ];
    }
}
