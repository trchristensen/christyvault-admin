<?php

namespace App\Filament\Operations\Resources\InventoryItemResource\Pages;

use App\Filament\Operations\Resources\InventoryItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInventoryItem extends EditRecord
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
