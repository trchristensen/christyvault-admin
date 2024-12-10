<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Customer;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function afterCreate(): void
    {
        // Get the created customer
        $customer = $this->record;

        // Get the form data
        $data = $this->data;

        // Create the location if location data exists
        if (isset($data['location'])) {
            // Add the customer name to the location data
            $locationData = array_merge($data['location'], [
                'name' => $customer->name
            ]);

            $customer->locations()->create($locationData);
        }
    }
}
