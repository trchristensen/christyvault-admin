<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocation extends EditRecord
{
    protected static string $resource = LocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        // Get the preferred contact ID
        $contactId = $this->data['preferred_delivery_contact_id'] ?? null;

        if ($contactId) {
            // Get the location
            $location = $this->record;

            // Create the relationship if it doesn't exist
            if (!$location->contacts()->where('contacts.id', $contactId)->exists()) {
                $location->contacts()->attach($contactId);
            }
        }
    }
}
