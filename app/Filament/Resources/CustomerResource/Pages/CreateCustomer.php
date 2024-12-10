<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function afterCreate(array $data): Customer
    {
        \Log::info('Customer data', [
            'data' => $data,
        ]);
        $customer = parent::afterCreate($data);
        $customer->locations()->create($data['location']);
        return $customer;
    }
}
