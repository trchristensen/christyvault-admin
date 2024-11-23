<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Location;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Create the customers (cemeteries as customers)
        $locations = Location::all();

        foreach ($locations as $location) {
            $customer = Customer::create([
                'name' => $location->name,
                'email' => strtolower(str_replace(' ', '', $location->name)) . '@example.com',
                'phone' => '(555) ' . rand(100, 999) . '-' . rand(1000, 9999),
            ]);

            // Attach the location to the customer
            $customer->locations()->attach($location->id, [
                'type' => 'primary',
                'sequence' => 0,
            ]);
        }
    }
}
