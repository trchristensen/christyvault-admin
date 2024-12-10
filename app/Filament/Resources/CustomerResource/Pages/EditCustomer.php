<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Models\Customer;
use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function afterSave(): void
    {
        // Force load the locations relationship
        $this->record->load('locations');

        \Log::info('Customer edit data', [
            'data' => $this->data,
            'record' => $this->record->load('locations')->toArray(),
            'has_locations' => $this->record->locations()->exists(),
        ]);

        if (isset($this->data['location'])) {
            $locationData = $this->data['location'];
            $locationData['name'] = $this->record->name;
            
            $location = $this->record->locations()->first();
            if ($location) {
                $location->update($locationData);
            } else {
                $this->record->locations()->create($locationData);
            }

            // Reload the relationship after creating/updating
            $this->record->load('locations');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
