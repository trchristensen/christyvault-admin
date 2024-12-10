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
            // Create a unique email by adding a random string
            $uniqueEmail = strtolower(
                str_replace(' ', '', $location->name) .
                    '-' .
                    substr(md5(uniqid()), 0, 6) .
                    '@example.com'
            );

            $customer = Customer::create([
                'name' => $location->name,
                'email' => $uniqueEmail,
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
