<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            // christy vault locations
            [
                'name' => 'Christy Vault - Colma',
                'address_line1' => '1000 Collins Ave',
                'city' => 'Colma',
                'state' => 'CA',
                'postal_code' => '94014',
                'location_type' => 'christy_vault',
                'latitude' => 37.6688,
                'longitude' => -122.4619,
                'radius_feet' => 1000,
            ],
            [
                'name' => 'Christy Vault - Tulare',
                'address_line1' => '9700 Ave 256',
                'city' => 'Tulare',
                'state' => 'CA',
                'postal_code' => '93274',
                'location_type' => 'christy_vault',
                'latitude' => 36.4415,
                'longitude' => -119.3850,
                'radius_feet' => 1000,
            ],

            [
                'name' => 'Skylawn Memorial Park',
                'address_line1' => 'Hwy 92 at Skyline Blvd',
                'city' => 'San Mateo',
                'state' => 'CA',
                'postal_code' => '94402',
                'location_type' => 'cemetery',
                'latitude' => 37.4320,
                'longitude' => -122.3426,
            ],
            [
                'name' => 'Cedar Lawn Memorial Park',
                'address_line1' => '48800 Warm Springs Blvd',
                'city' => 'Fremont',
                'state' => 'CA',
                'postal_code' => '94539',
                'location_type' => 'cemetery',
                'latitude' => 37.5027,
                'longitude' => -121.9269,
            ],
            [
                'name' => 'Cherokee Memorial Park',
                'address_line1' => '14165 N Beckman Rd',
                'city' => 'Lodi',
                'state' => 'CA',
                'postal_code' => '95240',
                'location_type' => 'cemetery',
                'latitude' => 38.1547,
                'longitude' => -121.2719,
            ],
            [
                'name' => 'Cypress Lawn Memorial Park',
                'address_line1' => '1370 El Camino Real',
                'city' => 'Colma',
                'state' => 'CA',
                'postal_code' => '94014',
                'location_type' => 'cemetery',
                'latitude' => 37.6688,
                'longitude' => -122.4619,
            ],
            [
                'name' => 'Los Gatos Memorial Park',
                'address_line1' => '2255 Los Gatos Almaden Rd',
                'city' => 'San Jose',
                'state' => 'CA',
                'postal_code' => '95124',
                'location_type' => 'cemetery',
                'latitude' => 37.2367,
                'longitude' => -121.9289,
            ],
        ];

        foreach ($locations as $location) {
            Location::create($location);
        }
    }
}
